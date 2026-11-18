<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\SeoTrackerEngine;

class SeoSettingsController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        if (session_status() === PHP_SESSION_NONE) session_start();

        // Procesar guardado de ajustes SEO
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['seo_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/seo-settings');
                exit;
            }

            $gaId          = trim($_POST['seo_ga_id'] ?? '');
            $headerScripts = trim($_POST['seo_header_scripts'] ?? '');

            if (strlen($headerScripts)>24000 || ($gaId!==''&&!preg_match('/^(?:G|UA)-[A-Z0-9-]{4,40}$/D',$gaId))) { http_response_code(422); exit('Identificador o scripts de SEO inválidos.'); }
            if ($headerScripts!==Settings::get('seo_header_scripts')) { try { \App\Core\ConsoleView::password(); } catch (\InvalidArgumentException $e) { http_response_code(422); exit('Confirma tu contraseña actual para publicar scripts.'); } }
            Settings::set('seo_ga_id', $gaId);
            Settings::set('seo_header_scripts', $headerScripts);

            $_SESSION['seo_status'] = ['type' => 'success', 'msg' => 'Ajustes del motor SEO & Analytics Tracker PRO guardados correctamente.'];
            header('Location: /admin/seo-settings');
            exit;
        }

        $gaId          = Settings::get('seo_ga_id', '');
        $headerScripts = Settings::get('seo_header_scripts', '');
        $isLicensed    = SeoTrackerEngine::isLicenseValid();

        $status = $_SESSION['seo_status'] ?? null;
        unset($_SESSION['seo_status']);

        $csrfToken = Csrf::generate();
        $title     = "Configuración SEO & Analytics Tracker PRO - GoldenSiteCMS";

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0; color: #fff;">
            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; margin-bottom: 0.25rem;">
                        📈 SEO & Analytics Tracker PRO
                    </h1>
                    <p style="color: #94a3b8; font-size: 0.95rem;">Gestiona la inyección automática de etiquetas meta y scripts de analítica.</p>
                </div>
                <div>
                    <?php if ($isLicensed): ?>
                        <span style="background: #15803d; color: #fff; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; font-weight: bold;">
                            ✅ LICENCIA ACTIVA
                        </span>
                    <?php else: ?>
                        <span style="background: #b91c1c; color: #fff; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; font-weight: bold;">
                            🔒 LICENCIA INACTIVA
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($status)): ?>
                <div style="background: <?= $status['type'] === 'success' ? '#15803d' : '#b91c1c' ?>; color: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold;">
                    <?= $status['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($status['msg']) ?>
                </div>
            <?php endif; ?>

            <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem;">
                <form action="/admin/seo-settings" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                    

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: bold; color: #cbd5e1; margin-bottom: 0.4rem;">
                            ID de Google Analytics (gtag.js):
                        </label>
                        <input type="text" name="seo_ga_id" value="<?= htmlspecialchars($gaId) ?>" placeholder="Ej: G-1234567890" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem;">
                        <span style="color: #64748b; font-size: 0.75rem;">Ingresa el identificador de medición para inyectar la etiqueta oficial en el &lt;head&gt;.</span>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: bold; color: #cbd5e1; margin-bottom: 0.4rem;">
                            Scripts y Etiquetas Personalizadas para el Header (&lt;head&gt;):
                        </label>
                        <textarea name="seo_header_scripts" rows="6" placeholder="<!-- Meta Pixel Code, Custom Tags, etc. -->" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #38bdf8; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem; font-family: monospace;"><?= htmlspecialchars($headerScripts) ?></textarea>
                        <span style="color: #64748b; font-size: 0.75rem;">Código HTML/JS adicional que se insertará antes del cierre de &lt;/head&gt;.</span>
                    </div>

                    <div style="text-align: right; margin-top: 1rem;">
                        <button type="submit" style="background: linear-gradient(135deg, #38bdf8, #0284c7); color: #0f172a; font-weight: bold; border: none; padding: 0.65rem 1.5rem; border-radius: 6px; cursor: pointer;">
                            Guardar Ajustes SEO 📈
                        </button>
                    </div>
                <label>Contraseña administrativa actual<input type="password" name="current_password" maxlength="72" autocomplete="current-password"></label></form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();

        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}