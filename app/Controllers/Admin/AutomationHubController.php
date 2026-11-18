<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class AutomationHubController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();
        self::initTables($db);

        self::purgeOldLogsByPolicy($db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['automation_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/automation-hub');
                exit;
            }

            foreach ($_POST as $value) if (!is_string($value)) { http_response_code(422); exit('Formato de campo inválido.'); }
            $action = $_POST['action'] ?? '';

            if ($action === 'create') {
                $name       = trim($_POST['name'] ?? 'Nueva Automatización');
                $trigger    = trim($_POST['trigger_event'] ?? 'post_published');
                $condIf     = trim($_POST['condition_field'] ?? '');
                $condOp     = trim($_POST['condition_operator'] ?? 'equals');
                $condVal    = trim($_POST['condition_value'] ?? '');
                $actionType = trim($_POST['action_type'] ?? 'send_email');
                $delay      = (int)($_POST['execution_delay'] ?? 0);
                $targetVal  = trim($_POST['target_value'] ?? '');

                if ($name==='' || (int)$db->query('SELECT COUNT(*) FROM goldensite_automations')->fetchColumn()>=200 || strlen($name) > 150 || strlen($targetVal) > 255 || strlen($condVal) > 100 || strlen($condIf) > 100 || !\App\Core\AutomationEngine::validRule(['action_type' => $actionType, 'target_value' => $targetVal, 'execution_delay' => $delay, 'condition_operator' => $condOp, 'trigger_event' => $trigger])) {
                    $_SESSION['automation_status'] = ['type' => 'danger', 'msg' => 'Regla inválida. Revisa evento, condición, acción y demora de 0 a 604800 segundos.'];
                    header('Location: /admin/automation-hub'); exit;
                }

                try {
                    $stmt = $db->prepare("INSERT INTO goldensite_automations (name, trigger_event, condition_field, condition_operator, condition_value, action_type, execution_delay, target_value) 
                                           VALUES (:n, :t, :cf, :co, :cv, :at, :ed, :tv)");
                    $stmt->execute([
                        'n' => $name, 't' => $trigger, 'cf' => $condIf, 'co' => $condOp,
                        'cv' => $condVal, 'at' => $actionType, 'ed' => $delay, 'tv' => $targetVal
                    ]);
                    $_SESSION['automation_status'] = ['type' => 'success', 'msg' => "Regla '{$name}' creada y activada en el sistema."];
                } catch (\Throwable $e) {
                    $_SESSION['automation_status'] = ['type' => 'danger', 'msg' => 'Error al guardar la regla.'];
                }
            } elseif ($action === 'test_trigger') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("SELECT * FROM goldensite_automations WHERE id = :id LIMIT 1");
                    $stmt->execute(['id' => $id]);
                    $rule = $stmt->fetch(); $ruleName = $rule['name'] ?? "Regla #{$id}";

                    $valid = $rule && \App\Core\AutomationEngine::validRule($rule);
                    $logStmt = $db->prepare("INSERT INTO goldensite_automation_logs (automation_name, status, details) VALUES (?, ?, 'Validación local de configuración. No se enviaron correos ni peticiones externas.')");
                    $logStmt->execute([$ruleName, $valid ? 'VALIDATED' : 'FAILED']);

                    $_SESSION['automation_status'] = ['type' => $valid ? 'success' : 'danger', 'msg' => $valid ? 'Configuración validada localmente; no se ejecutaron acciones externas.' : 'Configuración inválida; no se ejecutaron acciones.'];
                }
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $db->exec("UPDATE goldensite_automations SET is_active = IF(is_active = 1, 0, 1) WHERE id = {$id}");
                    $_SESSION['automation_status'] = ['type' => 'success', 'msg' => 'Estado de la regla actualizado.'];
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $db->exec("DELETE FROM goldensite_automations WHERE id = {$id}");
                    $_SESSION['automation_status'] = ['type' => 'success', 'msg' => 'Regla eliminada con éxito.'];
                }
            }

            header('Location: /admin/automation-hub');
            exit;
        }

        $automations = $db->query("SELECT * FROM goldensite_automations ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $logs        = $db->query("SELECT * FROM goldensite_automation_logs ORDER BY id DESC LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $csrfToken = Csrf::generate();
        $title     = "Goldensite Automation Hub - Webhooks & Automatizaciones";

        ob_start();
        require dirname(__DIR__, 3) . '/views/admin/automation.php';
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }

    private static function purgeOldLogsByPolicy(\PDO $db): void
    {
        try {
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'automation_log_retention' LIMIT 1");
            $days = (int)($stmt ? $stmt->fetchColumn() : 0);

            if ($days > 0) {
                $purgeStmt = $db->prepare("DELETE FROM goldensite_automation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
                $purgeStmt->execute(['days' => $days]);
            }
        } catch (\Throwable $e) {}
    }

    private static function initTables(\PDO $db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_automations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                trigger_event VARCHAR(100) NOT NULL,
                condition_field VARCHAR(100) NULL,
                condition_operator VARCHAR(50) DEFAULT 'equals',
                condition_value VARCHAR(100) NULL,
                action_type VARCHAR(100) NOT NULL,
                execution_delay INT DEFAULT 0,
                target_value VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_automation_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                automation_name VARCHAR(150) NOT NULL,
                status VARCHAR(20) NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }
}
