<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,CacheStore,ConsoleView,RequestGuard,Settings,SiteIcon};
final class MaintenanceController
{
    public function index(): void { Auth::requireAdmin(); $status=$_SESSION['maintenance_notice']??null; unset($_SESSION['maintenance_notice']);
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); $newImage=null; try { $message=ConsoleView::field($_POST,'maintenance_message',1000); $retry=ConsoleView::field($_POST,'retry_after',5)?:'3600'; if ($message===''||preg_match('/[<>]/',$message)||!ctype_digit($retry)||(int)$retry<60||(int)$retry>86400) throw new \InvalidArgumentException('Escribe un mensaje sin etiquetas y un intervalo de 60 a 86.400 segundos.'); $enabled=isset($_POST['maintenance_enabled'])?'1':'0'; $values=['maintenance_enabled'=>$enabled,'maintenance_mode'=>$enabled,'maintenance_message'=>$message,'maintenance_retry_after'=>$retry];
            if (isset($_FILES['maintenance_image'])&&($_FILES['maintenance_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) { $newImage=SiteIcon::upload($_FILES['maintenance_image']); $values['maintenance_image']=$newImage; }
            if (($_POST['remove_image']??'')==='1') $values['maintenance_image']=''; Settings::writeMany($values); CacheStore::clear(); ConsoleView::audit('maintenance_updated',$enabled==='1'?'Activado':'Desactivado'); $_SESSION['maintenance_notice']='Mantenimiento actualizado. El administrador conserva acceso.'; header('Location: /admin/maintenance'); return;
        } catch (\InvalidArgumentException $e) { if ($newImage) @unlink(dirname(__DIR__,3).'/public'.$newImage); http_response_code(422); $status=$e->getMessage(); } }
        ConsoleView::render('maintenance',['enabled'=>\App\Core\SiteConfiguration::maintenanceEnabled(),'message'=>Settings::get('maintenance_message','Estamos realizando mejoras. Volveremos pronto.'),'image'=>Settings::get('maintenance_image'),'retry'=>Settings::get('maintenance_retry_after','3600'),'status'=>$status],'Mantenimiento · Goldensite');
    }
}
