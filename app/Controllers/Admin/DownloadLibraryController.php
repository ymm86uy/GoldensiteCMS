<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,DownloadLibrary,Environment,IdentityAccess,RateLimiter,RequestGuard};
final class DownloadLibraryController
{
    public function index(): void
    {
        Auth::requireAdmin(); if (!IdentityAccess::superuser()) { http_response_code(403); return; } $db=Database::getInstance(); $notice=$_SESSION['download_notice']??null; unset($_SESSION['download_notice']); $newLink=$_SESSION['download_new_link']??null; unset($_SESSION['download_new_link']);
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $action=ConsoleView::field($_POST,'action',32); $id=filter_var($_POST['file_id']??0,FILTER_VALIDATE_INT)?:0;
            if ($action==='upload') { DownloadLibrary::upload($_POST,is_array($_FILES['file']??null)?$_FILES['file']:[]); $notice='Archivo guardado en almacenamiento privado con tamaño y checksum SHA-256.'; }
            elseif ($action==='update') { DownloadLibrary::update($id,$_POST); $notice='Metadatos y permisos actualizados. Los enlaces comprueban los permisos vigentes en cada acceso.'; }
            elseif ($action==='link') { $_SESSION['download_new_link']=rtrim(Environment::get('APP_URL'),'/').DownloadLibrary::createLink($id,$_POST); $notice='Enlace creado. Sus accesos se registran al autorizar la entrega; no equivalen a confirmación de descarga completa.'; }
            elseif ($action==='revoke') { DownloadLibrary::revoke(ConsoleView::field($_POST,'link_id',32)); $notice='Enlace revocado.'; }
            elseif ($action==='archive'||$action==='delete'||$action==='restore') { DownloadLibrary::state($id,$action==='restore'?'active':'archived'); $notice=$action==='restore'?'Recurso reactivado.':'Recurso archivado. Ninguno de sus enlaces admite nuevas entregas.'; }
            elseif ($action==='disable_legacy') { $db->execute('UPDATE goldensite_downloads SET allow_legacy_links=0 WHERE id=?',[$id]); ConsoleView::audit('download_legacy_links_disabled','file:'.$id); $notice='Enlaces antiguos sin registro retirados. Puedes crear enlaces revocables nuevos.'; }
            elseif ($action==='purge') { if (!RateLimiter::consume('download-purge:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.'); ConsoleView::password(); if (($_POST['confirm_delete']??'')!=='1') throw new \InvalidArgumentException('Confirma la eliminación definitiva.'); DownloadLibrary::purge($id); $notice='Archivo físico retirado y enlaces revocados. Se conserva el registro de actividad.'; }
            else throw new \InvalidArgumentException('Acción desconocida.');
            $_SESSION['download_notice']=$notice; header('Location: /admin/downloads'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $notice=$e->getMessage(); } }
        if (isset($_GET['admin_download'])) { $id=filter_var($_GET['admin_download'],FILTER_VALIDATE_INT)?:0; $file=$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status<>'deleted'",[$id]); $path=$file?DownloadLibrary::path($file):null; if (!$path) { http_response_code(404); return; } ConsoleView::audit('download_admin_read','file:'.$id); DownloadLibrary::stream($file+['resolved_path'=>$path]); }
        $q=ConsoleView::field($_GET,'q',100); $folder=ConsoleView::field($_GET,'folder',80); $page=filter_var($_GET['page']??1,FILTER_VALIDATE_INT)?:1; $page=max(1,min(10000,$page)); $where=" WHERE status<>'deleted'"; $params=[];
        if ($q!=='') { $where.=" AND (title LIKE ? ESCAPE '=' OR original_name LIKE ? ESCAPE '=')"; $pattern='%'.strtr($q,['='=>'==','%'=>'=%','_'=>'=_']).'%'; array_push($params,$pattern,$pattern); }
        if ($folder!=='') { $where.=' AND folder=?'; $params[]=$folder; }
        $total=(int)$db->fetch('SELECT COUNT(*) n FROM goldensite_downloads'.$where,$params)['n']; $files=$db->fetchAll('SELECT * FROM goldensite_downloads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params);
        foreach ($files as &$file) { $file['present']=DownloadLibrary::path($file)!==null; $file['links']=$db->fetchAll('SELECT * FROM goldensite_download_links WHERE file_id=? ORDER BY created_at DESC LIMIT 20',[$file['id']]); } unset($file);
        $selectedId=filter_var($_GET['edit']??0,FILTER_VALIDATE_INT)?:0; $selected=$selectedId?$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status<>'deleted'",[$selectedId]):null;
        ConsoleView::render('download_library',['catalogReady'=>\App\Core\Settings::get('joomla_catalog_enabled')==='1','notice'=>$notice,'newLink'=>$newLink,'q'=>$q,'folder'=>$folder,'files'=>$files,'selected'=>$selected,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/25),'users'=>$db->fetchAll("SELECT id,username FROM users WHERE status='active' ORDER BY username LIMIT 200"),'resources'=>$db->fetchAll("SELECT id,title,version_label FROM goldensite_downloads WHERE status<>'deleted' ORDER BY id DESC LIMIT 200"),'folders'=>$db->fetchAll("SELECT DISTINCT folder FROM goldensite_downloads WHERE status<>'deleted' ORDER BY folder"),'events'=>$db->fetchAll('SELECT e.*,d.title FROM goldensite_download_events e LEFT JOIN goldensite_downloads d ON d.id=e.file_id ORDER BY e.id DESC LIMIT 30')],'Goldensite Storage & Downloads');
    }
}
