<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,CacheStore,ConsoleView,Csrf,Database,Settings,Typography};
final class TypographyController
{
    public function index(): void {
        Auth::requireAdmin(); $status=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') { \App\Core\RequestGuard::post(); try {
            $action=ConsoleView::field($_POST,'action',20);
            if ($action==='global') { $a=Typography::validate($_POST); $backend=Typography::validate(['font'=>$_POST['admin_font']??'','size'=>$_POST['admin_size']??'','heading'=>$_POST['admin_heading']??'']); $front=ConsoleView::field($_POST,'site_language',2); $back=ConsoleView::field($_POST,'backend_language',2); if (!in_array($front,['es','en','pt'],true)||!in_array($back,['es','en','pt'],true)) throw new \InvalidArgumentException('Idioma no admitido.'); Settings::writeMany(['site_typography'=>json_encode($a,JSON_THROW_ON_ERROR),'admin_typography'=>json_encode($backend,JSON_THROW_ON_ERROR),'site_language'=>$front,'backend_language'=>$back]); }
            elseif ($action==='page') { $id=(int)($_POST['page_id']??0); $profile=Typography::validate($_POST,true); if (!Database::getInstance()->fetch('SELECT id FROM goldensite_pages WHERE id=?',[$id])) throw new \InvalidArgumentException('Página inexistente.'); Database::getInstance()->execute('UPDATE goldensite_pages SET typography_json=? WHERE id=?',[json_encode($profile,JSON_THROW_ON_ERROR),$id]); }
            elseif ($action==='translation') { $id=(int)($_POST['page_id']??0); $lang=ConsoleView::field($_POST,'language',2); $title=ConsoleView::field($_POST,'translated_title',255); $content=ConsoleView::field($_POST,'content',1048576); if (!\App\Core\IdentityAccess::superuser()) $content=\App\Core\CmsMigration::clean($content); if (!in_array($lang,['es','en','pt'],true)) throw new \InvalidArgumentException('Idioma inválido.'); $db=Database::getInstance()->getConnection(); $db->beginTransaction(); try { $stmt=$db->prepare('SELECT translations_json FROM goldensite_pages WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $row=$stmt->fetch(\PDO::FETCH_ASSOC); if (!$row) throw new \InvalidArgumentException('Página inexistente.'); $translations=json_decode($row['translations_json']??'{}',true)?:[]; $translations[$lang]=['title'=>$title,'content'=>$content]; $db->prepare('UPDATE goldensite_pages SET translations_json=? WHERE id=?')->execute([json_encode($translations,JSON_THROW_ON_ERROR),$id]); $db->commit(); } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; } }
            else throw new \InvalidArgumentException('Acción desconocida.');
            CacheStore::clear(); ConsoleView::audit('appearance_updated'); $_SESSION['type_status']='Preferencias guardadas.'; header('Location: /admin/typography'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $status??=$_SESSION['type_status']??null; unset($_SESSION['type_status']);
        ConsoleView::render('typography',['profile'=>Typography::global(),'adminProfile'=>Typography::global(true),'fonts'=>Typography::fonts(),'pages'=>Database::getInstance()->fetchAll('SELECT id,title,typography_json,translations_json FROM goldensite_pages ORDER BY id DESC'),'status'=>$status],'Tipografía e idiomas · Goldensite');
    }
}
