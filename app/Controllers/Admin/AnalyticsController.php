<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;

class AnalyticsController
{
    private static function checkTables($db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_analytics_hits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_url VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        self::checkTables($db);

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_analytics'])) {
            $db->exec("TRUNCATE TABLE goldensite_analytics_hits");
            $msg = "Métricas de tráfico purgadas con éxito.";
        }

        $totalHits = (int)$db->query("SELECT COUNT(*) FROM goldensite_analytics_hits")->fetchColumn();
        $topPages = $db->query("SELECT page_url, COUNT(*) as total FROM goldensite_analytics_hits GROUP BY page_url ORDER BY total DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $recentHits = $db->query("SELECT * FROM goldensite_analytics_hits ORDER BY id DESC LIMIT 15")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $title = "Goldensite Analytics & Live Traffic";

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
                        📈 Goldensite Analytics & Live Traffic
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Monitor de impresiones, páginas populares y tráfico del sitio web en tiempo real.</p>
                </div>
                <form method="POST" onsubmit="return confirm('¿Seguro que deseas reiniciar las métricas?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action_clear_analytics" value="1">
                    <button type="submit" style="background: #ef4444; color: #fff; font-weight: bold; border: none; padding: 0.65rem 1.25rem; border-radius: 0.75rem; font-size: 0.8rem; cursor: pointer;">Limpiar Métricas</button>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
                <div style="background: #1e293b; border: 1px solid #38bdf8; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Total de Impresiones</div>
                    <div style="font-size: 1.875rem; font-weight: 900; color: #38bdf8; margin-top: 0.25rem;"><?= number_format($totalHits) ?></div>
                </div>
                <div style="background: #1e293b; border: 1px solid #34d399; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Estado del Tracker</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #34d399; margin-top: 0.5rem;">● Registrando</div>
                </div>
                <div style="background: #1e293b; border: 1px solid #a855f7; padding: 1.25rem; border-radius: 1rem;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: bold; text-transform: uppercase;">Motor de Almacenamiento</div>
                    <div style="font-size: 1rem; font-weight: bold; color: #a855f7; margin-top: 0.5rem;">Local PDO / MySQL</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; color: #f8fafc; margin-bottom: 1rem;">🔥 Páginas más Visitadas</h3>
                    <?php if (empty($topPages)): ?>
                        <p style="font-size: 0.85rem; color: #94a3b8;">Sin datos recopilados aún.</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem;">
                            <?php foreach ($topPages as $page): ?>
                                <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #334155;">
                                    <span style="color: #38bdf8; font-weight: bold; word-break: break-all;"><?= htmlspecialchars($page['page_url']) ?></span>
                                    <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold; margin-left: 0.5rem;"><?= $page['total'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; color: #f8fafc; margin-bottom: 1rem;">⚡ Solicitudes en Tiempo Real</h3>
                    <?php if (empty($recentHits)): ?>
                        <p style="font-size: 0.85rem; color: #94a3b8;">No hay registros recientes.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                            <thead>
                                <tr style="color: #94a3b8; border-bottom: 1px solid #334155;">
                                    <th style="padding: 0.5rem;">Página</th>
                                    <th style="padding: 0.5rem;">IP</th>
                                    <th style="padding: 0.5rem;">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentHits as $hit): ?>
                                    <tr style="border-bottom: 1px solid rgba(51, 65, 85, 0.4);">
                                        <td style="padding: 0.5rem; color: #fff; text-overflow: ellipsis; max-width: 250px; overflow: hidden; whitespace: nowrap;"><?= htmlspecialchars($hit['page_url']) ?></td>
                                        <td style="padding: 0.5rem; color: #38bdf8; font-family: monospace;"><?= htmlspecialchars($hit['ip_address']) ?></td>
                                        <td style="padding: 0.5rem; color: #94a3b8;"><?= htmlspecialchars($hit['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}