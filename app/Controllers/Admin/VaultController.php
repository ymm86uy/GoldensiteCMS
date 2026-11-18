<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database};
final class VaultController
{
    public function index(): void { Auth::requireAdmin(); $db=Database::getInstance(); $count=(int)$db->fetch("SELECT (SELECT COUNT(*) FROM goldensite_pages WHERE content LIKE '%[goldensite_paywall]%')+(SELECT COUNT(*) FROM goldensite_posts WHERE content LIKE '%[goldensite_paywall]%') n")['n']; ConsoleView::render('vault',['protectedCount'=>$count],'Digital Vault & Paywall · Goldensite'); }
}
