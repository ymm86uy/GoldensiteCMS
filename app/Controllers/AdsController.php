<?php
namespace App\Controllers;

use App\Core\{AdminPath,Ads,Auth,CacheStore,ConsoleView,FrontendEngine,RequestGuard};

final class AdsController
{
    public function visit(string $id): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        $destination=Ads::visit($id,$_GET['page']??null,$_GET['sig']??null);
        if ($destination===null) { http_response_code(404); return; }
        header('Location: '.$destination,true,302);
    }

    public function index(): void
    {
        Auth::requireAdmin();
        $active = FrontendEngine::isModuleActive(Ads::ID);
        $notice = $_SESSION['ads_notice'] ?? '';
        unset($_SESSION['ads_notice']);
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            if (!$active) { http_response_code(409); return; }
            try {
                $id = ConsoleView::field($_POST,'id',16);
                $action = ConsoleView::field($_POST,'action',10);
                if ($action === 'save') $id = Ads::save($id,$_POST);
                elseif ($action === 'delete') Ads::delete($id);
                else throw new \InvalidArgumentException('Acción desconocida.');
                CacheStore::clear();
                ConsoleView::audit('ads_campaign_'.$action,$id);
                $_SESSION['ads_notice'] = $action==='save'?'Campaña guardada.':'Campaña eliminada.';
                header('Location: '.AdminPath::url('/admin/ads'),true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        ConsoleView::render('ads',['active'=>$active,'notice'=>$notice,'campaigns'=>Ads::campaigns(),'results'=>Ads::results()],'Goldensite ADS');
    }
}
