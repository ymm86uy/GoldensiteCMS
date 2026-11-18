<?php
namespace App\Controllers\Admin;

use App\Core\{AdminPath,Auth,ConsoleView,Database,Environment,FrontendEngine,RateLimiter,RequestGuard,S3Replica};

final class S3Controller
{
    public function index(): void
    {
        Auth::requireAdmin();
        header('Cache-Control: private, no-store');
        $active = FrontendEngine::isModuleActive('s3-connector');
        $configured = S3Replica::configured();
        $notice = $_SESSION['s3_notice']??'';
        unset($_SESSION['s3_notice']);
        $base = AdminPath::url('/admin/s3-connector');
        if ($active && $configured && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                if (!RateLimiter::consume('s3-administration:'.Auth::id(),10,300)) throw new \InvalidArgumentException('Espera antes de iniciar otra operación.');
                ConsoleView::password();
                $service = new S3Replica();
                $action = ConsoleView::field($_POST,'action',20);
                if ($action==='copy') {
                    $library = ConsoleView::field($_POST,'library',16);
                    $module = ['downloads'=>'server-downloads','dam'=>'dam'][$library]??null;
                    if (!$module || !FrontendEngine::isModuleActive($module)) throw new \InvalidArgumentException('Activa la biblioteca correspondiente antes de copiar un recurso.');
                    $service->copy($library,ConsoleView::field($_POST,'reference',255));
                } elseif ($action==='restore') {
                    $id = filter_var($_POST['replica']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    if ($id===false) throw new \InvalidArgumentException('Selecciona una copia.');
                    $service->restore($id);
                } else {
                    throw new \InvalidArgumentException('Acción inválida.');
                }
                ConsoleView::audit('s3_'.$action);
                $_SESSION['s3_notice'] = $action==='copy'?'Copia cifrada guardada en S3.':'Archivo restaurado y verificado.';
                header('Location: '.$base,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            } catch (\Throwable $error) {
                http_response_code(503);
                error_log('Operación S3 no completada; comprueba acceso, conectividad e integridad.');
                $notice = 'No se pudo completar la operación. Revisa el bucket, los permisos y la integridad de la copia.';
            }
        }
        $bucket = $configured?Environment::get('GOLDENSITE_S3_BUCKET'):'';
        $region = $configured?Environment::get('GOLDENSITE_S3_REGION'):'';
        $replicas = $active?Database::getInstance()->fetchAll('SELECT id,library,resource_ref,bytes,created_at,bucket,region FROM goldensite_s3_replicas ORDER BY created_at DESC,id DESC LIMIT 50'):[];
        $reference = is_string($_GET['reference']??null)?substr($_GET['reference'],0,255):'';
        $library = ($_GET['library']??'')==='dam'?'dam':'downloads';
        ConsoleView::render('s3_connector',compact('active','configured','notice','base','bucket','region','replicas','reference','library'),'Goldensite S3 Connector');
    }
}
