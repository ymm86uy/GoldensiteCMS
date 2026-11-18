<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;

class GdprController
{
    private static function checkTables($db): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_gdpr_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("INSERT IGNORE INTO goldensite_gdpr_settings (setting_key, setting_value) VALUES
                ('gdpr_enabled', '1'),
                ('banner_title', 'Tu privacidad y las cookies'),
                ('banner_text', 'Usamos cookies necesarias para que el sitio funcione. Tú decides si permites también los servicios opcionales.'),
                ('accept_btn_text', 'Aceptar opcionales'),
                ('reject_btn_text', 'Usar solo necesarias'),
                ('theme_color', '#bddd79'),
                ('position', 'bottom-right');");
        } catch (\Throwable $e) {}
    }

    private static function getSetting($db, string $key, string $default = ''): string
    {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM goldensite_gdpr_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $res = $stmt->fetchColumn();
            return $res !== false ? (string)$res : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        self::checkTables($db);

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_gdpr'])) {
            $enabled = isset($_POST['gdpr_enabled']) ? '1' : '0';
            try {
                $title = \App\Core\LicenseService::field($_POST, 'banner_title', 150);
                $text = \App\Core\LicenseService::field($_POST, 'banner_text', 1500);
                $btnAccept = \App\Core\LicenseService::field($_POST, 'accept_btn_text', 80);
                $btnReject = \App\Core\LicenseService::field($_POST, 'reject_btn_text', 80);
                $color = \App\Core\LicenseService::field($_POST, 'theme_color', 7) ?: '#ec4899';
                $pos = \App\Core\LicenseService::field($_POST, 'position', 20) ?: 'bottom-right';
                if (!preg_match('/^#[a-f0-9]{6}$/iD', $color) || !in_array($pos, ['bottom-right', 'bottom-left', 'top-bar'], true)) throw new \InvalidArgumentException('Color o posición inválidos.');
                $policy = [];
                foreach (['policy_responsible'=>150, 'policy_address'=>400, 'policy_email'=>254, 'policy_providers'=>3000] as $field=>$limit) {
                    $policy[$field] = array_key_exists($field, $_POST)
                        ? \App\Core\LicenseService::field($_POST, $field, $limit) : self::getSetting($db, $field);
                }
                if ($policy['policy_email'] !== '' && !filter_var($policy['policy_email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Indica un correo de privacidad válido.');
                }
            } catch (\InvalidArgumentException $error) { http_response_code(422); exit($error->getMessage()); }

            $db->prepare("REPLACE INTO goldensite_gdpr_settings (setting_key, setting_value) VALUES 
                ('gdpr_enabled', ?),
                ('banner_title', ?),
                ('banner_text', ?),
                ('accept_btn_text', ?),
                ('reject_btn_text', ?),
                ('theme_color', ?),
                ('position', ?),
                ('policy_responsible', ?),
                ('policy_address', ?),
                ('policy_email', ?),
                ('policy_providers', ?)")->execute([$enabled, $title, $text, $btnAccept, $btnReject, $color, $pos, ...array_values($policy)]);

            $msg = "Ajustes del banner GDPR guardados correctamente.";
        }

        $enabled = self::getSetting($db, 'gdpr_enabled', '1');
        $titleVal = self::getSetting($db, 'banner_title', 'Valoramos tu privacidad 🍪');
        $textVal = self::getSetting($db, 'banner_text', 'Utilizamos cookies para personalizar tu experiencia.');
        $btnAcceptVal = self::getSetting($db, 'accept_btn_text', 'Aceptar todas');
        $btnRejectVal = self::getSetting($db, 'reject_btn_text', 'Solo esenciales');
        $colorVal = self::getSetting($db, 'theme_color', '#ec4899');
        $posVal = self::getSetting($db, 'position', 'bottom-right');
        $policyValue = static fn(string $field): string => htmlspecialchars(self::getSetting($db, $field), ENT_QUOTES, 'UTF-8');

        $title = "Goldensite GDPR Consent - Administración";

        ob_start();
        ?>
        <div style="max-width: 1000px; margin: 0 auto; font-family: ui-sans-serif, system-ui; color: #f8fafc; padding-bottom: 3rem;">
            <?php if (!empty($msg)): ?>
                <div style="background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); color: #34d399; padding: 1rem; border-radius: 0.75rem; font-weight: bold; font-size: 0.85rem; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.25), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(236, 72, 153, 0.4); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.875rem; font-weight: 900; margin: 0 0 0.5rem 0; color: #f472b6;">
                        🍪 Goldensite GDPR & Cookie Banner
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Configuración del aviso legal y consentimientos de privacidad en el frontend.</p>
                </div>
            </div>

            <form method="POST" style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 1.75rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action_save_gdpr" value="1">

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                        <input type="checkbox" name="gdpr_enabled" value="1" <?= $enabled === '1' ? 'checked' : '' ?> style="width: 1.1rem; height: 1.1rem;">
                        <span style="font-weight: bold; color: #fff; font-size: 0.9rem;">Habilitar Banner GDPR en el Frontend</span>
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Título del Banner</label>
                        <input type="text" name="banner_title" value="<?= htmlspecialchars($titleVal) ?>" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Posición en Pantalla</label>
                        <select name="position" style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem; font-weight: bold;">
                            <option value="bottom-right" <?= $posVal === 'bottom-right' ? 'selected' : '' ?>>Flotante Abajo-Derecha</option>
                            <option value="bottom-left" <?= $posVal === 'bottom-left' ? 'selected' : '' ?>>Flotante Abajo-Izquierda</option>
                            <option value="top-bar" <?= $posVal === 'top-bar' ? 'selected' : '' ?>>Barra Superior</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Mensaje Explicativo</label>
                    <textarea name="banner_text" rows="3" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem;"><?= htmlspecialchars($textVal) ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Botón Aceptar</label>
                        <input type="text" name="accept_btn_text" value="<?= htmlspecialchars($btnAcceptVal) ?>" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Botón Rechazar</label>
                        <input type="text" name="reject_btn_text" value="<?= htmlspecialchars($btnRejectVal) ?>" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 8px; font-size: 0.85rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; font-weight: bold; margin-bottom: 0.3rem;">Color de Acento</label>
                        <input type="color" name="theme_color" value="<?= htmlspecialchars($colorVal) ?>" style="width: 100%; height: 38px; background: #0f172a; border: 1px solid #334155; padding: 0.2rem; border-radius: 8px; cursor: pointer;">
                    </div>
                </div>

                <fieldset class="nx-stack" style="margin-bottom:1.5rem">
                    <legend>Datos de la política de cookies</legend>
                    <p>Completa los datos del responsable y describe los proveedores opcionales activos, sus finalidades y la duración de sus cookies. <a href="/politica-cookies">Ver política pública</a></p>
                    <label>Nombre del responsable<input type="text" name="policy_responsible" maxlength="150" value="<?= $policyValue('policy_responsible') ?>"></label>
                    <label>Domicilio de contacto<textarea name="policy_address" maxlength="400" rows="2"><?= $policyValue('policy_address') ?></textarea></label>
                    <label>Correo de privacidad<input type="email" name="policy_email" maxlength="254" value="<?= $policyValue('policy_email') ?>"></label>
                    <label>Proveedores y cookies opcionales<textarea name="policy_providers" maxlength="3000" rows="4"><?= $policyValue('policy_providers') ?></textarea></label>
                </fieldset>
                <button type="submit" style="background: #f472b6; color: #0f172a; font-weight: 900; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 0.85rem; cursor: pointer;">Guardar Configuración GDPR</button>
            </form>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}
