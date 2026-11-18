<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;

class SitemapAdminController
{
    private static function checkTables($db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_sitemap_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("INSERT IGNORE INTO goldensite_sitemap_settings (setting_key, setting_value) VALUES
                ('auto_sitemap', '1'),
                ('robots_content', 'User-agent: *\nDisallow: /admin/\nDisallow: /api/\n');");
        } catch (\Throwable $e) {}
    }

    public static function generateXml(): string
    {
        return (new \App\Controllers\SitemapController())->document();
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        self::checkTables($db);

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_sitemap'])) {
            $robots = trim($_POST['robots_content'] ?? '');
            $db->prepare("REPLACE INTO goldensite_sitemap_settings (setting_key, setting_value) VALUES ('robots_content', ?)")->execute([$robots]);




            $msg = "🗺️ Configuración guardada. sitemap.xml y robots.txt se actualizan automáticamente con el contenido publicado.";
        }

        $robotsVal = '';
        try {
            $stmt = $db->prepare("SELECT setting_value FROM goldensite_sitemap_settings WHERE setting_key = 'robots_content' LIMIT 1");
            $stmt->execute();
            $robotsVal = (string)$stmt->fetchColumn();
        } catch (\Throwable $e) {}

        $title = "Goldensite Sitemap Generator";

        ob_start();
        ?>
        <div style="max-width: 1000px; margin: 0 auto; font-family: ui-sans-serif, system-ui; color: #f8fafc; padding-bottom: 3rem;">
            <?php if (!empty($msg)): ?>
                <div style="background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); color: #34d399; padding: 1rem; border-radius: 0.75rem; font-weight: bold; font-size: 0.85rem; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, rgba(56, 189, 248, 0.25), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.875rem; font-weight: 900; margin: 0 0 0.5rem 0; color: #38bdf8;">
                        🗺️ Goldensite Sitemap & Robots SEO
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Generación automática de mapa del sitio dinámico y control de rastreadores web.</p>
                </div>
            </div>

            <form method="POST" style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.75rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action_save_sitemap" value="1">

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.4rem;">Configuración de robots.txt</label>
                    <textarea name="robots_content" rows="6" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #38bdf8; font-family: monospace; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem;"><?= htmlspecialchars($robotsVal) ?></textarea>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button type="submit" style="background: #38bdf8; color: #0f172a; font-weight: 900; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.85rem; cursor: pointer;">Generar XML & Actualizar Robots</button>
                    <a href="/sitemap.xml" target="_blank" style="color: #f472b6; font-size: 0.8rem; font-weight: bold; text-decoration: none;">Ver sitemap.xml ↗</a>
                </div>
            </form>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}