<?php
namespace App\Core;
final class Navigation
{
    public static function safeUrl(string $url): bool { if (strlen($url)>255||preg_match('/[\s\\\\<>\x00-\x1f]/',$url)) return false; if (str_starts_with($url,'/')&&!str_starts_with($url,'//')) return true; $p=parse_url($url); return $p&&($p['scheme']??'')==='https'&&!empty($p['host'])&&!isset($p['user'])&&!isset($p['pass']); }
    public static function target(array $item): array {
        $db=Database::getInstance(); $url=$item['url']; if (!self::safeUrl($url)) return ['ok'=>false,'label'=>'Dirección no segura','url'=>$url];
        if (!empty($item['page_id'])) { $page=$db->fetch('SELECT slug,status,is_published FROM goldensite_pages WHERE id=?',[(int)$item['page_id']]); return ['ok'=>$page&&$page['status']==='published'&&(int)$page['is_published']===1,'label'=>$page?'Página vinculada':'Página eliminada','url'=>$page?'/page/'.$page['slug']:$url]; }
        if (str_starts_with($url,'https://')) return ['ok'=>true,'label'=>'Enlace externo HTTPS','url'=>$url];
        $path=rawurldecode(parse_url($url,PHP_URL_PATH)?:'/');
        if (in_array($path,['/','/blog','/contacto','/faq','/login','/register','/registro','/mi-cuenta','/account/security'],true)) return ['ok'=>true,'label'=>'Página del sistema','url'=>$url];
        if (in_array($path,['/tienda','/cart','/checkout'],true)) return ['ok'=>FrontendEngine::isModuleActive('commerce-sales'),'label'=>'Tienda','url'=>$url];
        if ($path==='/reservas') return ['ok'=>FrontendEngine::isModuleActive('booking'),'label'=>'Reservas','url'=>$url];
        if ($path==='/academia') return ['ok'=>FrontendEngine::isModuleActive('academy'),'label'=>'Academia','url'=>$url];
        if ($path==='/eventos') return ['ok'=>FrontendEngine::isModuleActive('events'),'label'=>'Eventos','url'=>$url];
        if (in_array($path,['/chat','/marketplace','/dating'],true)) return ['ok'=>FrontendEngine::isModuleActive(['chat'=>'live-chat','marketplace'=>'marketplace','dating'=>'dating'][ltrim($path,'/')]),'label'=>'Módulo público','url'=>$url];
        if (preg_match('~^/forms/([^/]+)$~',$path,$m)) return ['ok'=>FrontendEngine::isModuleActive('form-builder')&&(bool)$db->fetch('SELECT id FROM goldensite_forms WHERE slug=? AND is_active=1',[$m[1]]),'label'=>'Formulario','url'=>$url];
        if (preg_match('~^/blog/([^/]+)$~',$path,$m)) return ['ok'=>(bool)$db->fetch("SELECT id FROM goldensite_posts WHERE slug=? AND status='published'",[$m[1]]),'label'=>'Artículo','url'=>$url];
        if (preg_match('~^/(?:page/)?([^/]+)$~',$path,$m)&&$db->fetch("SELECT id FROM goldensite_pages WHERE slug=? AND status='published' AND is_published=1",[$m[1]])) return ['ok'=>true,'label'=>'Página publicada','url'=>$url];
        if (FrontendEngine::isModuleActive('monitor-redirects')&&$db->fetch('SELECT id FROM goldensite_redirects WHERE source_url=?',[$path])) return ['ok'=>true,'label'=>'Redirección configurada','url'=>$url];
        if (preg_match('~^/(?:uploads|assets|download|producto)/~',$path)) return ['ok'=>true,'label'=>'Recurso del sitio','url'=>$url];
        return ['ok'=>false,'label'=>'Destino no disponible','url'=>$url];
    }
    public static function publicItems(): array {
        $items=Database::getInstance()->fetchAll('SELECT * FROM goldensite_menus WHERE is_active=1 ORDER BY sort_order,id'); $out=[]; $logged=Auth::check();
        foreach ($items as $item) { if (($item['visibility']==='logged_in'&&!$logged)||($item['visibility']==='admin'&&(!$logged||!IdentityAccess::canEnter()))) continue; $target=self::target($item); if (!$target['ok']) continue; $item['url']=$target['url']; $out[]=$item; } return $out;
    }
    public static function render(): string {
        $items=self::publicItems(); $groups=[]; foreach ($items as $item) $groups[(int)$item['parent_id']][]=$item; $seen=[]; $e=ConsoleView::escape(...);
        $walk=function(int $parent,int $depth=0) use (&$walk,&$seen,$groups,$e): string { if ($depth>4) return ''; $html=''; foreach ($groups[$parent]??[] as $item) { $id=(int)$item['id']; if (isset($seen[$id])) continue; $seen[$id]=true; $children=$walk($id,$depth+1); $link='<a href="'.$e($item['url']).'"'.($item['is_target_blank']?' target="_blank" rel="noopener noreferrer"':'').' data-nx-content>'.$e(html_entity_decode($item['title'],ENT_QUOTES,'UTF-8')).'</a>'; $html.=$children?'<details class="nx-nav-dropdown"><summary>'.$link.'<span aria-hidden="true">⌄</span></summary><div class="nx-nav-submenu">'.$children.'</div></details>':'<div class="nav-item">'.$link.'</div>'; } return $html; };
        $html='<nav class="nav-links" aria-label="'.Locale::text('Navegación').'"><div class="nav-item"><a href="/">'.Locale::text('Inicio').'</a></div>'.$walk(0);
        if (!array_filter($items,static fn($i)=>parse_url($i['url'],PHP_URL_PATH)==='/contacto')) $html.='<div class="nav-item"><a href="/contacto">'.Locale::text('Contacto').'</a></div>';
        $html.='<form class="search-box" method="get" action="/buscar" role="search"><input name="q" type="search" id="searchInput" placeholder="'.Locale::text('Buscar').'…" aria-label="'.Locale::text('Buscar').'" autocomplete="off"><div id="searchResults" class="search-results"></div></form>';
        $html.=Auth::check()?'<a href="'.(IdentityAccess::canEnter()?AdminPath::url('/admin/dashboard'):'/mi-cuenta').'" class="btn-cta">'.Locale::text(IdentityAccess::canEnter()?'Panel Admin':'Mi cuenta').'</a>':'<a href="/login" class="btn-cta">'.Locale::text('Iniciar sesión').'</a>';
        return $html.'</nav>';
    }
    public static function contentDeleted(int $id,string $slug,bool $post=false): void { $urls=$post?['/blog/'.$slug]:['/'.$slug,'/page/'.$slug]; $params=$urls; $where='url IN ('.implode(',',array_fill(0,count($urls),'?')).')'; if (!$post) { $where.=' OR page_id=?'; $params[]=$id; } Database::getInstance()->execute('UPDATE goldensite_menus SET is_active=0 WHERE '.$where,$params); }
}
