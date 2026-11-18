<?php
namespace App\Controllers\Admin;
use App\Core\{Accessibility,Auth,ConsoleView,RequestGuard,Settings};
final class AccessibilityController
{
    public function index(): void { Auth::requireAdmin(); $status=null; if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try { $features=$_POST['features']??[]; $position=ConsoleView::field($_POST,'position',10); if (!is_array($features)||!array_is_list($features)||array_filter($features,static fn($v)=>!is_string($v))||array_diff($features,array_keys(Accessibility::FEATURES))||!in_array($position,['left','right'],true)) throw new \InvalidArgumentException('Revisa los controles y la posición.'); Settings::set('accessibility_config',json_encode(['enabled'=>isset($_POST['enabled']),'position'=>$position,'features'=>$features],JSON_THROW_ON_ERROR)); ConsoleView::audit('accessibility_updated'); header('Location: /admin/accessibility'); return; } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } } ConsoleView::render('accessibility',['config'=>Accessibility::config(),'status'=>$status],'Accesibilidad · Goldensite'); }
}
