<?php

namespace App\Controllers\Admin;

use App\Core\{AdminPath,Auth,ConsoleView,Database,ExtensionPackages,IdentityAccess,OwnerAccess,ProductEdition,ReleaseUpdate};

class PluginController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();
        $msg = $_SESSION['extension_notice']??''; $errorMsg = '';
        unset($_SESSION['extension_notice']);
        $ownerEdition=ProductEdition::current()==='owner';
        $fullAdmin=IdentityAccess::superuser();
        $ownerUnlocked=$ownerEdition && OwnerAccess::unlocked();
        $catalogUrl=AdminPath::url('/admin/plugins');
        $extensionOffers=[];
        $customPrices = \App\Core\ModuleCatalog::prices();
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['extension_action'])) {
                    if (!$fullAdmin) { http_response_code(403); return; }
                    $this->extensionAction($ownerEdition,$ownerUnlocked,$catalogUrl);
                    return;
                }
                if (isset($_POST['import_catalog'])) {
                    \App\Core\ModuleCommerce::import(\App\Core\ConsoleView::field($_POST,'catalog_code',131072)); header('Location: /admin/plugins'); return;
                }
                if (isset($_POST['import_revocations'])) {
                    $code = $_POST['revocation_code'] ?? '';
                    if (!is_string($code)) throw new \InvalidArgumentException('Lista inválida.');
                    \App\Core\LicenseService::importRevocations($code);
                    header('Location: /admin/plugins'); return;
                }
                $modId = \App\Core\ModuleCatalog::canonical(\App\Core\LicenseService::field($_POST, 'module_id', 100));
                if (!\App\Core\ModuleCatalog::get($modId)) throw new \InvalidArgumentException('Módulo desconocido.');
                if (isset($_POST['action_save_license'])) {
                    \App\Core\LicenseService::activate($modId, \App\Core\LicenseService::field($_POST, 'license_key', 2048));
                    $msg = 'Licencia firmada verificada y módulo activado para el dominio del sitio.';
                } elseif (isset($_POST['action_toggle_module'])) {
                    if ($modId === 'edge-waf') throw new \InvalidArgumentException('El WAF se gestiona desde el centro de seguridad.');
                    $state = \App\Core\LicenseService::field($_POST, 'toggle_state', 1);
                    if (!in_array($state, ['0', '1'], true)) throw new \InvalidArgumentException('Estado inválido.');
                    if ($state === '1' && \App\Core\ModuleCatalog::isPro($modId) && !\App\Core\LicenseEngine::isLicenseActive($modId)) { http_response_code(403); exit('Este módulo requiere una licencia válida para el dominio del sitio.'); }
                    $db->prepare('INSERT INTO goldensite_active_modules (module_id, is_active) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)')->execute([$modId, (int)$state]);
                    \App\Core\FrontendEngine::resetModuleCache();
                    \App\Core\LicenseService::audit($state === '1' ? 'module_enabled' : 'module_disabled', $modId);
                    $msg = $state === '1' ? 'Módulo activado.' : 'Módulo desactivado.';
                }
            }
        } catch (\InvalidArgumentException|\JsonException $e) { http_response_code(422); $errorMsg = $e instanceof \JsonException ? 'Código de licencia inválido.' : $e->getMessage(); }
        if (!$ownerEdition && $fullAdmin && ($_GET['channel']??'')==='official') {
            try { $extensionOffers=ExtensionPackages::offers(); }
            catch (\Throwable $error) { $errorMsg='El canal oficial de extensiones no está disponible o no pudo verificarse.'; }
        }
        $installedExtensions=ExtensionPackages::installed();
        $coreVersion=ReleaseUpdate::version();
        $isModuleActive = static fn($id) => \App\Core\FrontendEngine::isModuleActive($id);
        $modules = \App\Core\ModuleCatalog::all();
        $title = 'Módulos y extensiones · Goldensite';
        ob_start(); require dirname(__DIR__,3).'/views/admin/module_catalog.php'; $content=ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }

    private function extensionAction(bool $ownerEdition,bool $ownerUnlocked,string $catalogUrl): void
    {
        try {
            ConsoleView::password();
            $action=ConsoleView::field($_POST,'extension_action',16);
            $code=ConsoleView::field($_POST,'extension_signature',2048);
            if ($action==='publish' && $ownerUnlocked) {
                $upload=$_FILES['extension_zip']??[];
                $path=$upload['tmp_name']??null;
                if (($upload['error']??null)!==UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
                    throw new \InvalidArgumentException('Selecciona un ZIP de extensión firmado.');
                }
                $id=ExtensionPackages::install($path,$code,true);
                $message='Extensión instalada en Owner y publicada en el canal oficial.';
            } elseif ($action==='install' && !$ownerEdition) {
                $id=ExtensionPackages::import($code);
                $message='Extensión instalada. Gestiona su activación desde el catálogo.';
            } elseif ($action==='rollback' && (!$ownerEdition || $ownerUnlocked)) {
                $id=ConsoleView::field($_POST,'extension_id',46);
                ExtensionPackages::rollback($id,ConsoleView::field($_POST,'extension_current',64));
                $message=$ownerEdition
                    ?'Versión anterior recuperada. La publicación de esta extensión quedó retirada del canal oficial.'
                    :'Versión anterior recuperada. Los datos de la extensión se conservan.';
            } elseif ($action==='withdraw' && $ownerUnlocked) {
                $id=ConsoleView::field($_POST,'extension_id',46);
                ExtensionPackages::withdraw($id,ConsoleView::field($_POST,'extension_current',64));
                $message='Extensión retirada del canal. Sigue instalada en los sitios que ya la tenían.';
            } elseif ($action==='bundled_forum' && (!$ownerEdition || $ownerUnlocked)) {
                $id=\App\Core\ForumDistribution::ID;
                ExtensionPackages::useBundledForum(ConsoleView::field($_POST,'extension_current',64));
                $message='Forum volvió a la versión incluida en GoldenSiteCMS. Sus datos y activación se conservan.';
            } else {
                throw new \InvalidArgumentException('Operación no disponible para esta cuenta o edición.');
            }
            ConsoleView::audit('extension_'.$action,$id);
            $_SESSION['extension_notice']=$message;
        } catch (\InvalidArgumentException $error) {
            $_SESSION['extension_notice']=$error->getMessage();
        } catch (\Throwable $error) {
            error_log('GoldenSiteCMS extensiones: '.$error->getMessage());
            $_SESSION['extension_notice']='No se pudo verificar o guardar la extensión. Revisa el registro del servidor.';
        }
        header('Location: '.$catalogUrl.'#official-extensions',true,303);
    }
}
