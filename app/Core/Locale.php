<?php
namespace App\Core;
final class Locale
{
    public static function language(bool $admin=false): string { $value=$admin?Settings::get('backend_language','es'):($_COOKIE['goldensite_language']??Settings::get('site_language','es')); return is_string($value)&&in_array($value,['es','en','pt'],true)?$value:'es'; }
    public static function text(string $text,bool $admin=false): string { $lang=self::language($admin); if ($lang==='es') return $text; static $catalog; $catalog??=array_merge(require dirname(__DIR__,2).'/config/locales.php',require dirname(__DIR__,2).'/config/locales-extra.php'); return $catalog[$text][$lang]??$text; }
    public static function html(string $html,bool $admin=false): string {
        $lang=self::language($admin); $html=preg_replace('/(<html\b[^>]*\blang=")[^"]*(")/i','$1'.$lang.'$2',$html,1);
        if ($lang==='es'&&!$admin) return $html;
        $prior=libxml_use_internal_errors(true); $dom=new \DOMDocument('1.0','UTF-8');
        try { if (!$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET)) return $html;
            $xpath=new \DOMXPath($dom);
            foreach ($xpath->query('//text()[not(ancestor::script or ancestor::style or ancestor::textarea or ancestor::pre or ancestor::code or ancestor::*[@data-nx-content])]') as $node) { $raw=$node->nodeValue; $trim=trim($raw); if ($trim==='') continue; $translated=self::text($trim,$admin); if ($trim!==$translated) $node->nodeValue=substr($raw,0,strlen($raw)-strlen(ltrim($raw))).$translated.substr($raw,strlen(rtrim($raw))); }
            foreach ($xpath->query('//*[@placeholder or @aria-label or @title]') as $node) foreach (['placeholder','aria-label','title'] as $a) if ($node->hasAttribute($a)) $node->setAttribute($a,self::text($node->getAttribute($a),$admin));
            if ($admin) foreach (iterator_to_array($xpath->query('//text()[not(ancestor::script or ancestor::style or ancestor::textarea or ancestor::pre or ancestor::code or ancestor::title or ancestor::*[@data-nx-content])]')) as $node) {
                $parts=preg_split('/([\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]\x{FE0F}?)/u',$node->nodeValue,-1,PREG_SPLIT_DELIM_CAPTURE); if (!$parts||count($parts)<2) continue;
                $map=['📊'=>'dashboard','📋'=>'activity-log','📄'=>'pages','📝'=>'posts','📧'=>'messages','📩'=>'messages','🔒'=>'identity-access','🔐'=>'identity-access','🔑'=>'owner','⚙'=>'settings','🛡'=>'waf','🎨'=>'themes','🚀'=>'deploy-manager','📦'=>'deploy-manager','🔌'=>'plugins','✨'=>'ai-builder','✅'=>'waf','❌'=>'waf','🗑'=>'settings','💾'=>'deploy-manager','💬'=>'messages','📁'=>'pages','🔗'=>'menus'];
                $fragment=$dom->createDocumentFragment(); foreach ($parts as $part) { if (preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u',$part)) { $name=$map[rtrim($part,"\u{FE0F}")]??'goldensite'; $svg=new \DOMDocument(); $svg->loadXML(GoldensiteIcon::svg($name,20),LIBXML_NONET); $fragment->appendChild($dom->importNode($svg->documentElement,true)); } else $fragment->appendChild($dom->createTextNode($part)); } $node->parentNode->replaceChild($fragment,$node);
            }
            foreach (iterator_to_array($dom->childNodes) as $n) if ($n->nodeType===XML_PI_NODE) $dom->removeChild($n);
            return $dom->saveHTML();
        } finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    }
    public static function selector(): string { $lang=self::language(); $return=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; $h='<form method="post" action="/language" class="nx-language-switch" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="return_to" value="'.ConsoleView::escape($return).'"><input type="hidden" name="csrf_token" value="'.ConsoleView::escape(Csrf::generate()).'"><label style="font-size:12px">'.self::text('Idioma').'<select name="language" aria-label="'.self::text('Idioma').'" style="padding:7px;background:#171b20;color:#fff;border:1px solid #59606b;border-radius:6px">'; foreach (['es'=>'Español','en'=>'English','pt'=>'Português'] as $k=>$label) $h.='<option value="'.$k.'" '.($lang===$k?'selected':'').'>'.$label.'</option>'; return $h.'</select></label><button type="submit" style="padding:7px">'.self::text('Aplicar').'</button></form>'; }
}
