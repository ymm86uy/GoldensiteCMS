<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath, Auth, ConsoleView, Database, DeployManager, IdentityAccess, OwnerAccess, OwnerUpdatePublication, ProductEdition, RateLimiter, RequestGuard};
final class DeployController
{
    public function index(): void {
        if (ProductEdition::current()!=='owner') { http_response_code(404); return; }
        Auth::requireAdmin(); $db=Database::getInstance(); $status=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try { $action=ConsoleView::field($_POST,'action',20); $id=(int)($_POST['release_id']??0);
            if ($action==='publish-update') { $this->publishUpdate(); return; }
            if ($action==='retire-update') { $this->retireUpdate(); return; }
            if ($action==='stage') { $label=ConsoleView::field($_POST,'label',120); if ($label==='') throw new \InvalidArgumentException('Escribe un nombre para la versión.'); $id=DeployManager::stage($label); }
            elseif ($action==='edit') DeployManager::edit($id,$_POST,ConsoleView::field($_POST,'checksum',64));
            elseif ($action==='publish') { ConsoleView::password(); if (($_POST['confirmation']??'')!=='PUBLICAR') throw new \InvalidArgumentException('Escribe PUBLICAR para confirmar.'); DeployManager::publish($id,ConsoleView::field($_POST,'checksum',64)); }
            else throw new \InvalidArgumentException('Acción desconocida.'); ConsoleView::audit('deploy_'.$action,'Versión #'.$id); header('Location: /admin/deploy-manager?id='.$id); return;
        } catch (\InvalidArgumentException|\RuntimeException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $releases=$db->fetchAll('SELECT id,label,state,checksum,created_at,published_at FROM goldensite_deploy_releases ORDER BY id DESC LIMIT 100'); $selected=$db->fetch('SELECT * FROM goldensite_deploy_releases WHERE id=?',[(int)($_GET['id']??0)]); $snapshot=$selected?DeployManager::load($selected):null;
        $showUpdatePublication = ProductEdition::current() === 'owner' && IdentityAccess::superuser();
        $canPublishUpdate = $showUpdatePublication && OwnerAccess::unlocked();
        $commercialReleases=[];
        if ($showUpdatePublication) {
            try { $commercialReleases=OwnerUpdatePublication::published(); }
            catch (\InvalidArgumentException $e) { $status??=$e->getMessage(); }
        }
        $commercialFrom=is_string($_GET['commercial']??null)?$_GET['commercial']:'';
        $selectedCommercial=$commercialReleases[$commercialFrom]??null;
        $updateMessage = $_SESSION['deploy_update_message'] ?? null;
        unset($_SESSION['deploy_update_message']);
        ConsoleView::render('deploy',compact('releases','selected','snapshot','status','showUpdatePublication','canPublishUpdate','updateMessage','commercialReleases','selectedCommercial'),'Goldensite Deploy Manager');
    }

    private function retireUpdate(): void
    {
        if (ProductEdition::current()!=='owner') { http_response_code(404); return; }
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        OwnerAccess::requireUnlocked();
        if (!RateLimiter::consume('commercial-retire:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera unos minutos antes de intentar otra retirada.');
        ConsoleView::password();
        if (($_POST['confirmation']??'')!=='ELIMINAR') throw new \InvalidArgumentException('Escribe ELIMINAR para confirmar.');
        try {
            $release=OwnerUpdatePublication::retire(ConsoleView::field($_POST,'commercial_from',20),ConsoleView::field($_POST,'notice_fingerprint',64));
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable $error) {
            error_log('Retirada comercial: '.$error->getMessage());
            throw new \RuntimeException('No se pudo completar la retirada. Comprueba el canal y el registro del servidor.');
        }
        $message='Actualización comercial retirada de '.$release['from'].' a '.$release['version'].'. Se guardó una copia privada.';
        $_SESSION['deploy_update_message']=$message;
        ConsoleView::audit('deploy_commercial_update_retired',$message);
        header('Location: '.AdminPath::url('/admin/deploy-manager').'#deploy-versions',true,303);
    }

    private function publishUpdate(): void
    {
        if (ProductEdition::current() !== 'owner') { http_response_code(404); return; }
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        OwnerAccess::requireUnlocked();
        if (!RateLimiter::consume('commercial-publish:'.Auth::id(), 5, 300)) {
            throw new \InvalidArgumentException('Espera unos minutos antes de intentar otra publicación.');
        }
        ConsoleView::password();
        $uploads = [];
        foreach (['update_zip', 'update_notice'] as $field) {
            $file = $_FILES[$field] ?? [];
            $temp = $file['tmp_name'] ?? null;
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($temp) || !is_uploaded_file($temp)) {
                throw new \InvalidArgumentException('Adjunta el ZIP y el aviso .nfx completos. Revisa el límite de carga del servidor.');
            }
            $uploads[$field] = $temp;
        }
        try {
            $release = OwnerUpdatePublication::publish($uploads['update_zip'], $uploads['update_notice']);
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable $error) {
            error_log('Publicación comercial: '.$error->getMessage());
            throw new \RuntimeException('No se pudo publicar la actualización. Consulta el registro del servidor.');
        }
        $message = 'Actualización comercial publicada de '.$release['from'].' a '.$release['version'].'.';
        $_SESSION['deploy_update_message'] = $message;
        ConsoleView::audit('deploy_commercial_update_published', $message);
        header('Location: '.AdminPath::url('/admin/deploy-manager'), true, 303);
    }
}
