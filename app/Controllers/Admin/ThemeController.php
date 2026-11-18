<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,Csrf,RequestGuard,ThemeCatalog,ThemeService};
final class ThemeController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            try { $id = $_POST['theme_folder'] ?? ''; if (!is_string($id)) throw new \InvalidArgumentException('Selecciona un estilo válido.'); ThemeService::activate($id); $_SESSION['theme_status'] = ['type'=>'success','msg'=>'Estilo activado. La portada y las páginas usan ahora este tema; la composición personalizada permanece guardada en el estudio.']; }
            catch (\InvalidArgumentException $e) { http_response_code(422); $status = ['type'=>'danger','msg'=>$e->getMessage()]; }
            if (!isset($status)) { header('Location: /admin/themes'); return; }
        }
        $themes = ThemeCatalog::all(); $activeTheme = ThemeService::active(); $status ??= $_SESSION['theme_status'] ?? null; unset($_SESSION['theme_status']);
        $title = 'Identidad visual · Goldensite'; $csrfToken = Csrf::generate();
        ob_start(); require dirname(__DIR__,3).'/views/admin/themes.php'; $content = ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }
}
