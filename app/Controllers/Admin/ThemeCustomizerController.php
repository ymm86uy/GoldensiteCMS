<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,Csrf,RequestGuard,ThemeService};
final class ThemeCustomizerController
{
    public function index(): void
    {
        Auth::requireAdmin(); $status = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            try { if (($_POST['action']??'')==='reset') { ThemeService::resetProfile(\App\Core\LicenseService::field($_POST,'theme_id',50)); $message='Paleta y efectos originales restaurados para este estilo.'; } else { ThemeService::save($_POST); $message='Personalización guardada para el estilo activo.'; } $_SESSION['theme_customizer_status'] = ['type'=>'success','msg'=>$message]; header('Location: /admin/themes/customize'); return; }
            catch (\InvalidArgumentException $e) { http_response_code(422); $status = ['type'=>'danger','msg'=>$e->getMessage()]; }
        }
        $profile = ThemeService::profile(); $activeTheme = ThemeService::active(); $status ??= $_SESSION['theme_customizer_status'] ?? null; unset($_SESSION['theme_customizer_status']);
        $title = 'Personalizar estilo · Goldensite'; $csrfToken = Csrf::generate(); ob_start(); require dirname(__DIR__,3).'/views/admin/theme_customizer.php'; $content = ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }
}
