<?php
namespace App\Controllers;
use App\Core\{Auth,ConsoleView,Database,FrontendEngine,IdentityAccess};
final class AccountController
{
    public function index(): void
    {
        Auth::requireAuth();
        header('Cache-Control: no-store, private');
        $user=Auth::user();
        $adminAccount=IdentityAccess::superuser()||IdentityAccess::role()!==null;
        $security=Database::getInstance()->fetch('SELECT totp_secret FROM users WHERE id=?',[Auth::id()]);
        $secondFactor=!empty($security['totp_secret']);
        $datingActive=FrontendEngine::isModuleActive('dating');
        $chatActive=FrontendEngine::isModuleActive('live-chat');
        $e=ConsoleView::escape(...); $siteTitle='Mi cuenta · GoldenSiteCMS';
        ob_start(); require dirname(__DIR__,2).'/views/account/overview.php';
        $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
