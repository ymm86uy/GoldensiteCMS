<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;

class ActivityLogController
{
    private static function checkTables($db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                username VARCHAR(100) DEFAULT 'Sistema',
                action VARCHAR(100) NOT NULL,
                description TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT '',
                user_agent VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public static function log(string $action, string $description = ''): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            self::checkTables($db);

            $user = Auth::user();
            $userId = $user['id'] ?? null;
            $username = $user['username'] ?? $user['name'] ?? 'Invitado / Sistema';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmt = $db->prepare("INSERT INTO goldensite_activity_logs (user_id, username, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $username, $action, $description, $ip, $agent]);
        } catch (\Throwable $e) {}
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        self::checkTables($db);

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_logs'])) {
            $db->exec("TRUNCATE TABLE goldensite_activity_logs");
            $msg = "Historial de actividad purgado correctamente.";
        }

        $logs = $db->query("SELECT * FROM goldensite_activity_logs ORDER BY id DESC LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $totalLogs = (int)$db->query("SELECT COUNT(*) FROM goldensite_activity_logs")->fetchColumn();

        $title = "Goldensite Activity Log - Registro de Auditoría";

        ob_start();
        ?>
        <div style="max-width: 1200px; margin: 0 auto; font-family: ui-sans-serif, system-ui; color: #f8fafc; padding-bottom: 3rem;">
            <?php if (!empty($msg)): ?>
                <div style="background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); color: #34d399; padding: 1rem; border-radius: 0.75rem; font-weight: bold; font-size: 0.85rem; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, rgba(56, 189, 248, 0.25), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.875rem; font-weight: 900; margin: 0 0 0.5rem 0; color: #38bdf8;">
                        📋 Goldensite Activity & Audit Log
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Módulo de auditoría de seguridad para rastrear eventos e interacciones del sistema.</p>
                </div>
                <form method="POST" onsubmit="return confirm('¿Seguro que deseas purgar el registro de auditoría?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action_clear_logs" value="1">
                    <button type="submit" style="background: #ef4444; color: #fff; font-weight: bold; border: none; padding: 0.65rem 1.25rem; border-radius: 0.75rem; font-size: 0.8rem; cursor: pointer;">Limpiar Logs</button>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
                <div style="background: #1e293b; border: 1px solid #38bdf8; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Total de Registros</div>
                    <div style="font-size: 1.875rem; font-weight: 900; color: #38bdf8; margin-top: 0.25rem;"><?= number_format($totalLogs) ?></div>
                </div>
                <div style="background: #1e293b; border: 1px solid #34d399; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Estado del Módulo</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #34d399; margin-top: 0.5rem;">● Activo</div>
                </div>
                <div style="background: #1e293b; border: 1px solid #a855f7; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Ubicación</div>
                    <div style="font-size: 0.95rem; font-weight: bold; color: #a855f7; margin-top: 0.5rem;">Registro de actividad</div>
                </div>
            </div>

            <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: bold; color: #f8fafc; margin-bottom: 1rem;">📜 Registros de Auditoría Recientes</h3>
                <?php if (empty($logs)): ?>
                    <p style="font-size: 0.85rem; color: #94a3b8; text-align: center; padding: 2rem 0;">No hay entradas registradas en el log.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="color: #94a3b8; border-bottom: 1px solid #334155;">
                                <th style="padding: 0.75rem;">ID</th>
                                <th style="padding: 0.75rem;">Usuario</th>
                                <th style="padding: 0.75rem;">Acción</th>
                                <th style="padding: 0.75rem;">Descripción</th>
                                <th style="padding: 0.75rem;">Dirección IP</th>
                                <th style="padding: 0.75rem;">Fecha y Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4);">
                                    <td style="padding: 0.75rem; color: #94a3b8; font-family: monospace;"><?= $log['id'] ?></td>
                                    <td style="padding: 0.75rem; font-weight: bold; color: #fff;"><?= htmlspecialchars($log['username']) ?></td>
                                    <td style="padding: 0.75rem;"><span style="background: rgba(244, 114, 182, 0.15); color: #f472b6; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold; font-size: 0.75rem; text-transform: uppercase;"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td style="padding: 0.75rem; color: #cbd5e1; font-size: 0.8rem;"><?= htmlspecialchars($log['description']) ?></td>
                                    <td style="padding: 0.75rem; color: #38bdf8; font-family: monospace; font-size: 0.75rem;"><?= htmlspecialchars($log['ip_address']) ?></td>
                                    <td style="padding: 0.75rem; color: #94a3b8; font-size: 0.75rem;"><?= htmlspecialchars($log['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}