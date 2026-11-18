<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;

class AssetOptimizerController
{
    private static function checkTables($db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_optimizer_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("INSERT IGNORE INTO goldensite_optimizer_settings (setting_key, setting_value) VALUES
                ('minify_html', '1'),
                ('webp_auto_convert', '1'),
                ('cache_max_age', '86400');");
        } catch (\Throwable $e) {}
    }

    private static function getSetting($db, string $key, string $default = ''): string
    {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM goldensite_optimizer_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $res = $stmt->fetchColumn();
            return $res !== false ? (string)$res : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function index(): void
    {
        $this->renderAdmin();
    }

    public function renderAdmin(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        self::checkTables($db);

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_optimizer'])) {
            $minifyHtml = isset($_POST['minify_html']) ? '1' : '0';
            $webpAuto = '0';
            $cacheAge = 0;

            $db->prepare("REPLACE INTO goldensite_optimizer_settings (setting_key, setting_value) VALUES 
                ('minify_html', ?),
                ('webp_auto_convert', ?),
                ('cache_max_age', ?)")->execute([$minifyHtml, $webpAuto, $cacheAge]);

            $msg = "⚡ Ajustes de optimización de rendimiento guardados correctamente.";
        }

        $minifyHtml = self::getSetting($db, 'minify_html', '1');
        $webpAuto = self::getSetting($db, 'webp_auto_convert', '1');
        $cacheAge = self::getSetting($db, 'cache_max_age', '86400');

        $title = "Goldensite Asset Optimizer";

        ob_start();
        ?>
        <div style="max-width: 1000px; margin: 0 auto; font-family: ui-sans-serif, system-ui; color: #f8fafc; padding-bottom: 3rem;">
            <?php if (!empty($msg)): ?>
                <div style="background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); color: #34d399; padding: 1rem; border-radius: 0.75rem; font-weight: bold; font-size: 0.85rem; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, rgba(52, 211, 153, 0.25), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(52, 211, 153, 0.4); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.875rem; font-weight: 900; margin: 0 0 0.5rem 0; color: #34d399;">
                        ⚡ Goldensite Asset Optimizer & Performance
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Compresión de HTML en tiempo real, caché de navegador y optimización de velocidad de carga.</p>
                </div>
            </div>

            <form method="POST" style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.75rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action_save_optimizer" value="1">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.75rem; background: #0f172a; padding: 1rem; border-radius: 10px; border: 1px solid #334155; cursor: pointer;">
                        <input type="checkbox" name="minify_html" value="1" <?= $minifyHtml === '1' ? 'checked' : '' ?> style="width: 1.1rem; height: 1.1rem;">
                        <div>
                            <span style="font-weight: bold; color: #fff; font-size: 0.85rem; display: block;">Minificar HTML al vuelo</span>
                            <span style="font-size: 0.7rem; color: #94a3b8;">Elimina comentarios no esenciales sin alterar espacios, scripts ni texto preformateado.</span>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; gap: 0.75rem; background: #0f172a; padding: 1rem; border-radius: 10px; border: 1px solid #334155; cursor: pointer;">
                        <input type="checkbox" disabled style="width: 1.1rem; height: 1.1rem;">
                        <div>
                            <span style="font-weight: bold; color: #fff; font-size: 0.85rem; display: block;">Conversión Automática WebP</span>
                            <span style="font-size: 0.7rem; color: #94a3b8;">No disponible: requiere un procesador de imágenes. Puedes subir imágenes WebP ya optimizadas.</span>
                        </div>
                    </label>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.4rem;">Tiempo de Caché Navegador (Max-Age en Segundos)</label>
                    <p>La caché de archivos estáticos se configura en Apache/Nginx. Las respuestas con sesión conservan su protección.</p><select disabled style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #34d399; font-weight: bold; padding: 0.75rem; border-radius: 8px; font-size: 0.85rem;">
                        <option value="3600" <?= $cacheAge === '3600' ? 'selected' : '' ?>>1 Hora (3600s)</option>
                        <option value="86400" <?= $cacheAge === '86400' ? 'selected' : '' ?>>24 Horas (86400s - Recomendado)</option>
                        <option value="604800" <?= $cacheAge === '604800' ? 'selected' : '' ?>>7 Días (604800s)</option>
                    </select>
                </div>

                <button type="submit" style="background: #34d399; color: #0f172a; font-weight: 900; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.85rem; cursor: pointer;">Guardar Reglas de Optimización</button>
            </form>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}
