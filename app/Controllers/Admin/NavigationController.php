<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,CacheStore,ConsoleView,Database,IdentityAccess,Navigation,RequestGuard};
final class NavigationController
{
    public function index(): void { Auth::requireAdmin(); $db=Database::getInstance(); $status=$_SESSION['nav_notice']??null; unset($_SESSION['nav_notice']);
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $action=ConsoleView::field($_POST,'action',20); $id=(int)($_POST['id']??0);
            if ($action==='delete') { $db->getConnection()->beginTransaction(); try { $db->execute('UPDATE goldensite_menus SET parent_id=0 WHERE parent_id=?',[$id]); $db->execute('DELETE FROM goldensite_menus WHERE id=?',[$id]); $db->getConnection()->commit(); } catch (\Throwable $e) { $db->getConnection()->rollBack(); throw $e; } }
            elseif (in_array($action,['add','save'],true)) {
                $title=ConsoleView::field($_POST,'title',150); $pageId=(int)($_POST['page_id']??0); $url=ConsoleView::field($_POST,'url',255); $parent=(int)($_POST['parent_id']??0); $visibility=ConsoleView::field($_POST,'visibility',20)?:'all'; $order=filter_var($_POST['sort_order']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>9999]]);
                if ($pageId) { $page=$db->fetch('SELECT slug FROM goldensite_pages WHERE id=?',[$pageId]); if (!$page) throw new \InvalidArgumentException('Página inexistente.'); $url='/page/'.$page['slug']; }
                if ($title===''||preg_match('/[<>]/',$title)||!Navigation::safeUrl($url)||!in_array($visibility,['all','logged_in','admin'],true)||$order===false) throw new \InvalidArgumentException('Revisa nombre, destino, visibilidad y orden. Usa una ruta interna o un enlace HTTPS.');
                if ($id&&!$db->fetch('SELECT id FROM goldensite_menus WHERE id=?',[$id])) throw new \InvalidArgumentException('Enlace inexistente.');
                $visited=[$id]; $cursor=$parent; $depth=0; while ($cursor) { if (in_array($cursor,$visited,true)||++$depth>4) throw new \InvalidArgumentException('La jerarquía contiene un ciclo o supera cuatro niveles.'); $visited[]=$cursor; $row=$db->fetch('SELECT parent_id FROM goldensite_menus WHERE id=?',[$cursor]); if (!$row) throw new \InvalidArgumentException('El enlace superior no existe.'); $cursor=(int)$row['parent_id']; }
                $values=[$title,$url,$pageId?:null,$parent,$order,$visibility,($_POST['is_active']??'')==='1'||$action==='add'?1:0,isset($_POST['is_target_blank'])?1:0];
                if ($id) $db->execute('UPDATE goldensite_menus SET title=?,url=?,page_id=?,parent_id=?,sort_order=?,visibility=?,is_active=?,is_target_blank=? WHERE id=?',[...$values,$id]); else $db->execute('INSERT INTO goldensite_menus (title,url,page_id,parent_id,sort_order,visibility,is_active,is_target_blank) VALUES (?,?,?,?,?,?,?,?)',$values);
            } else throw new \InvalidArgumentException('Acción desconocida.'); CacheStore::clear(); ConsoleView::audit('navigation_'.$action,'Enlace #'.$id); $_SESSION['nav_notice']='Navegación actualizada.'; header('Location: /admin/menus'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $items=$db->fetchAll('SELECT * FROM goldensite_menus ORDER BY parent_id,sort_order,id'); foreach ($items as &$item) $item['target']=Navigation::target($item); unset($item);
        ConsoleView::render('navigation',['items'=>$items,'selected'=>$db->fetch('SELECT * FROM goldensite_menus WHERE id=?',[(int)($_GET['id']??0)]),'pages'=>$db->fetchAll('SELECT id,title,status FROM goldensite_pages ORDER BY title'),'status'=>$status],'Navegación · Goldensite');
    }
}
