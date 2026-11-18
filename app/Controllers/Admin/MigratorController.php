<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ContentImporter,Csrf,RequestGuard};
final class MigratorController
{
    public static function isTrialExpired(): bool { return !\App\Core\LicenseEngine::isLicenseActive('universal-importer'); }
    public function index(): void
    {
        Auth::requireAdmin(); $title = 'Importación de artículos JSON';
        ob_start(); require dirname(__DIR__, 3) . '/views/admin/migrator.php'; $content = ob_get_clean();
        require dirname(__DIR__, 3) . '/views/layouts/admin.php';
    }
    public function processImport(): void
    {
        Auth::requireAdmin(); RequestGuard::post();
        try {
            $file = $_FILES['export_file'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || filesize($file['tmp_name']) > 5242880) throw new \InvalidArgumentException('Selecciona un archivo JSON de hasta 5 MB.');
            $count = ContentImporter::import(file_get_contents($file['tmp_name']));
            $_SESSION['migrator_status'] = ['type' => 'success', 'msg' => "$count artículos importados como borradores. Revisa el contenido antes de publicarlo."];
        } catch (\InvalidArgumentException $e) { $_SESSION['migrator_status'] = ['type' => 'danger', 'msg' => $e->getMessage()]; }
        catch (\Throwable $e) { error_log('Content import failed: ' . $e->getMessage()); $_SESSION['migrator_status'] = ['type' => 'danger', 'msg' => 'No se pudo importar. No se ha confirmado ningún cambio.']; }
        header('Location: /admin/importer');
    }
}
