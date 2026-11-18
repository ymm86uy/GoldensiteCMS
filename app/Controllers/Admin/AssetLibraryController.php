<?php
namespace App\Controllers\Admin;
use App\Core\{AssetLibrary,Auth,ConsoleView,RequestGuard};
final class AssetLibraryController
{
    public function index(): void { Auth::requireAdmin(); $status=$_SESSION['asset_notice']??null; unset($_SESSION['asset_notice']); if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try { $path=ConsoleView::field($_POST,'asset_path',255); $url=AssetLibrary::webp($path); ConsoleView::audit('asset_webp_created'); $_SESSION['asset_notice']='Copia WebP generada: '.$url.'. El original se conserva.'; header('Location: /admin/dam'); return; } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } } $q=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):''; ConsoleView::render('asset_library',['items'=>AssetLibrary::items($q),'q'=>$q,'status'=>$status],'Biblioteca de activos · Goldensite'); }
}
