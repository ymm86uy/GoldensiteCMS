<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth,ConsoleView,Database,Environment,FrontendEngine,IdentityAccess,RateLimiter,RequestGuard};
use Modules\GoldensiteSecurityAudit\SecurityScanner;

final class SecurityAuditController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        header('Cache-Control: private, no-store');
        $report=null; $notice=null;
        if (!FrontendEngine::isModuleActive('security-audit')) {
            http_response_code(403);
            $notice='Activa Goldensite Security Audit desde Módulos y extensiones.';
        } elseif (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            RequestGuard::post();
            if (!RateLimiter::consume('security-audit:'.Auth::id(),1,60)) {
                http_response_code(429);
                $notice='Espera un minuto antes de repetir la auditoría.';
            } else {
                $scanner=new SecurityScanner(Database::getInstance()->getConnection(),dirname(__DIR__,3),Environment::get('APP_URL'));
                $report=$scanner->scan();
                ConsoleView::audit('security_audit_run',count($report['findings']).' hallazgos');
            }
        }
        ConsoleView::render('security_audit/report',compact('report','notice'),'Auditoría de seguridad');
    }
}
