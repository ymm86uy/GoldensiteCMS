<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth,ConsoleView,Database,Environment,FrontendEngine,IdentityAccess,RateLimiter,RequestGuard};
use Modules\GoldensiteSecurityAudit\{SecurityScanner,AuditHistory};

final class SecurityAuditController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        header('Cache-Control: private, no-store');
        $report=null; $notice=null; $history=[]; $selectedAudit=null;
        $active=FrontendEngine::isModuleActive('security-audit');
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
                try {
                    $selectedAudit=(new AuditHistory(Database::getInstance()->getConnection()))->save($report,(int)Auth::id(),\App\Core\ReleaseUpdate::version(),\App\Core\ProductEdition::current());
                    $report=$selectedAudit['report'];
                } catch (\Throwable $error) {
                    error_log('Security audit history save failed ('.get_class($error).').');
                    $notice='La auditoría terminó, pero no se pudo guardar el historial. Revisa la migración de esta versión.';
                }
                ConsoleView::audit('security_audit_run',count($report['findings']).' hallazgos');
            }
        }
        if ($active) {
            try {
                $store=new AuditHistory(Database::getInstance()->getConnection()); $history=$store->recent();
                if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && isset($_GET['audit'])) {
                    $id=filter_var($_GET['audit'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    $selectedAudit=$id!==false?$store->find($id):null;
                    if (!$selectedAudit) { http_response_code(404); $notice='El informe no existe o quedó fuera de los últimos 30 registros.'; }
                    else $report=$selectedAudit['report'];
                }
            } catch (\Throwable $error) {
                error_log('Security audit history read failed ('.get_class($error).').');
                $notice??='El historial no está disponible. Revisa la migración de esta versión.';
            }
        }
        ConsoleView::render('security_audit/report',compact('report','notice','history','selectedAudit'),'Auditoría de seguridad');
    }
}
