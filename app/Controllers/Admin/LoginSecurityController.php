<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LoginAppearance;

class LoginSecurityController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $db = Database::getInstance()->getConnection();
        $isPro = \App\Core\LicenseEngine::isLicenseActive('login-security');
        $preview = $_GET['preview'] ?? null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $preview !== null) {
            if (!is_string($preview) || !LoginAppearance::available($preview)) {
                http_response_code(404);
                return;
            }
            header('Cache-Control: no-store, private');
            $loginPreview = $preview;
            require dirname(__DIR__, 3).'/views/auth/login.php';
            return;
        }

        // Crear tabla si no existe
        $db->exec("CREATE TABLE IF NOT EXISTS goldensite_login_studio (
            id INT PRIMARY KEY DEFAULT 1,
            active_style VARCHAR(50) DEFAULT 'glass_neon',
            bg_image_url TEXT NULL,
            company_logo_url TEXT NULL,
            company_name VARCHAR(150) DEFAULT 'GoldenSiteCMS',
            company_tagline VARCHAR(255) DEFAULT 'Acceso al Sistema',
            accent_color VARCHAR(20) DEFAULT '#38bdf8',
            enable_captcha TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selectedStyle = is_string($_POST['active_style'] ?? null) ? $_POST['active_style'] : 'glass_neon';
            if (!LoginAppearance::available($selectedStyle)) { http_response_code(422); exit('Estilo de acceso inválido.'); }

            if (!$isPro && !in_array($selectedStyle, LoginAppearance::FREE_STYLES, true)) {
                $selectedStyle = 'glass_neon';
                $msg = "⚠️ El estilo seleccionado requiere una Licencia PRO activa.";
            } else {
                try {
                    $bgUrl = \App\Core\LicenseService::field($_POST, 'bg_image_url', 2048);
                    $logoUrl = \App\Core\LicenseService::field($_POST, 'company_logo_url', 2048);
                    $compName = \App\Core\LicenseService::field($_POST, 'company_name', 150) ?: 'GoldenSiteCMS';
                    $compTagline = \App\Core\LicenseService::field($_POST, 'company_tagline', 255);
                    $color = \App\Core\LicenseService::field($_POST, 'accent_color', 7) ?: '#38bdf8';
                    if (!preg_match('/^#[a-f0-9]{6}$/iD', $color) || !\App\Core\LoginAppearance::media($bgUrl) || !\App\Core\LoginAppearance::media($logoUrl)) throw new \InvalidArgumentException('Usa un color hexadecimal y URLs HTTPS o de /uploads/.');
                } catch (\InvalidArgumentException $error) { http_response_code(422); exit($error->getMessage()); }
                $captcha = 0;

                $stmt = $db->prepare("INSERT INTO goldensite_login_studio (id, active_style, bg_image_url, company_logo_url, company_name, company_tagline, accent_color, enable_captcha) 
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE active_style = ?, bg_image_url = ?, company_logo_url = ?, company_name = ?, company_tagline = ?, accent_color = ?, enable_captcha = ?");
                $stmt->execute([
                    $selectedStyle, $bgUrl, $logoUrl, $compName, $compTagline, $color, $captcha,
                    $selectedStyle, $bgUrl, $logoUrl, $compName, $compTagline, $color, $captcha
                ]);
                $msg = "✨ Configuración corporativa del Login guardada correctamente.";
            }
        }

        $stmt = $db->query("SELECT * FROM goldensite_login_studio WHERE id = 1");
        $settings = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'active_style' => LoginAppearance::defaultStyle(),
            'bg_image_url' => '',
            'company_logo_url' => '',
            'company_name' => 'GoldenSiteCMS',
            'company_tagline' => 'Acceso al Sistema',
            'accent_color' => '#38bdf8',
            'enable_captcha' => 0
        ];

        $templates = [
            ['id' => 'glass_neon', 'name' => '1. Glassmorphism Neón', 'pro' => false, 'icon' => '🌌', 'desc' => 'Tarjeta translúcida con brillo perimetral neón.'],
            ['id' => 'split_national', 'name' => '2. Split NatGeo (2 Col)', 'pro' => false, 'icon' => '🖼️', 'desc' => 'Diseño dividido con banner fotográfico a la izquierda.'],
            ['id' => 'nature_mist', 'name' => '3. Nature Misty Forest', 'pro' => true, 'icon' => '🌲', 'desc' => 'Fondo de naturaleza con cabecera verde y tarjeta blanca.'],
            ['id' => 'cyberpunk_glow', 'name' => '4. Cyberpunk Glow', 'pro' => true, 'icon' => '⚡', 'desc' => 'Estilo futurista púrpura con botones brillantes.'],
            ['id' => 'minimal_clean', 'name' => '5. Minimal White Clean', 'pro' => true, 'icon' => '📄', 'desc' => 'Formulario sobrio corporativo de alto contraste.'],
            ['id' => 'dark_wallet', 'name' => '6. Dark Crypto Wallet', 'pro' => true, 'icon' => '💳', 'desc' => 'Estilo inspirado en Web3 con inputs redondeados.'],
            ['id' => 'gradient_sunset', 'name' => '7. Gradient Sunset', 'pro' => true, 'icon' => '🌅', 'desc' => 'Degradado fluido en tonos violeta y naranja.'],
            ['id' => 'auth_centered_badge', 'name' => '8. Centered Avatar Badge', 'pro' => true, 'icon' => '👤', 'desc' => 'Insignia flotante superior con enfoque en perfil.']
        ];
        foreach (LoginAppearance::MOTION as $id => [$name, $description, $accent]) {
            if (!LoginAppearance::available($id)) continue;
            $templates[] = ['id'=>$id, 'name'=>$name, 'pro'=>$id !== 'owner_atlas', 'icon'=>'', 'desc'=>$description, 'accent'=>$accent];
        }
        $templates = array_values(array_filter($templates, static fn (array $template): bool => LoginAppearance::available($template['id'])));

        $title = "Goldensite Custom Login & Security Studio";
        ob_start();
        ?>
        <div style="max-width: 1100px; margin: 0 auto; color: #fff; font-family: ui-sans-serif, system-ui; padding-bottom: 3rem;">
            <?php if (!empty($msg)): ?>
                <div style="background: rgba(56, 189, 248, 0.2); border: 1px solid #38bdf8; color: #38bdf8; padding: 1rem; border-radius: 12px; font-weight: bold; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="background: linear-gradient(135deg, rgba(56, 189, 248, 0.2), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 900; margin: 0 0 0.5rem 0; color: #38bdf8;">
                        Goldensite Custom Login <?= $isPro ? 'PRO' : '' ?>
                    </h1>
                    <p style="color: #cbd5e1; font-size: 0.9rem; margin: 0;">Selecciona la plantilla de acceso pública y personaliza la identidad visual corporativa.</p>
                </div>
            </div>

            <form method="POST" style="display: flex; flex-direction: column; gap: 2rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 2rem;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; margin-bottom: 1.25rem; color: #fff;">Plantillas de acceso (<?= count($templates) ?>)</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.25rem;">
                        <?php foreach ($templates as $tpl): 
                            $isSelected = ($settings['active_style'] === $tpl['id']);
                            $isDisabled = ($tpl['pro'] && !$isPro);
                        ?>
                            <div style="display:grid;grid-template-rows:1fr auto;gap:.6rem">
                            <label style="position: relative; background: #0f172a; border: 2px solid <?= $isSelected ? '#38bdf8' : '#334155' ?>; border-radius: 1rem; padding: 1.25rem; cursor: <?= $isDisabled ? 'not-allowed' : 'pointer' ?>; opacity: <?= $isDisabled ? '0.5' : '1' ?>; display: flex; flex-direction: column; justify-content: space-between;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                    <?php if (isset($tpl['accent'])): ?>
                                        <span aria-hidden="true" style="display:block;width:72px;height:32px;border-radius:8px;background:radial-gradient(ellipse at 30% 80%,<?= htmlspecialchars($tpl['accent']) ?>,transparent 70%),#172137"></span>
                                    <?php else: ?>
                                        <span aria-hidden="true" style="font-size: 1.8rem;"><?= $tpl['icon'] ?></span>
                                    <?php endif; ?>
                                    <input type="radio" name="active_style" value="<?= $tpl['id'] ?>" <?= $isSelected ? 'checked' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>>
                                </div>
                                <div>
                                    <strong style="display: block; color: #fff; font-size: 0.95rem; margin-bottom: 0.25rem;"><?= htmlspecialchars($tpl['name']) ?></strong>
                                    <p style="font-size: 0.75rem; color: #94a3b8; line-height: 1.4; margin-bottom: 0.75rem;"><?= htmlspecialchars($tpl['desc']) ?></p>
                                </div>
                                <div>
                                    <?php if ($tpl['pro']): ?>
                                        <span style="background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #f59e0b; font-size: 0.65rem; font-weight: bold; padding: 0.15rem 0.5rem; border-radius: 4px;">REQUERIMIENTO PRO</span>
                                    <?php else: ?>
                                        <span style="background: rgba(52, 211, 153, 0.2); border: 1px solid #34d399; color: #34d399; font-size: 0.65rem; font-weight: bold; padding: 0.15rem 0.5rem; border-radius: 4px;"><?= $tpl['id'] === 'owner_atlas' ? 'EDICIÓN OWNER' : 'INCLUIDO FREE' ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <a class="nx-btn" href="<?= htmlspecialchars(\App\Core\AdminPath::url('/admin/login-security').'?preview='.rawurlencode($tpl['id'])) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars('Vista previa de '.$tpl['name']) ?>">Vista previa ↗</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; padding: 2rem; opacity: <?= $isPro ? '1' : '0.6' ?>;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; margin-bottom: 1.25rem; color: #f59e0b;">
                        🏢 Personalización de Marca, Imágenes y Estilos (PRO)
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 0.4rem; color: #cbd5e1;">Nombre de la Empresa</label>
                            <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? 'GoldenSiteCMS') ?>" <?= $isPro ? '' : 'disabled' ?> style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.75rem; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 0.4rem; color: #cbd5e1;">Subtítulo de Login</label>
                            <input type="text" name="company_tagline" value="<?= htmlspecialchars($settings['company_tagline'] ?? 'Acceso al Sistema') ?>" <?= $isPro ? '' : 'disabled' ?> style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.75rem; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 0.4rem; color: #cbd5e1;">URL de Logo Corporativo</label>
                            <input type="url" name="company_logo_url" value="<?= htmlspecialchars($settings['company_logo_url'] ?? '') ?>" <?= $isPro ? '' : 'disabled' ?> style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.75rem; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 0.4rem; color: #cbd5e1;">URL de Imagen de Fondo</label>
                            <input type="url" name="bg_image_url" value="<?= htmlspecialchars($settings['bg_image_url'] ?? '') ?>" <?= $isPro ? '' : 'disabled' ?> style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.75rem; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 0.4rem; color: #cbd5e1;">Color de Acento / Botones</label>
                            <input type="color" name="accent_color" value="<?= htmlspecialchars($settings['accent_color'] ?? '#38bdf8') ?>" <?= $isPro ? '' : 'disabled' ?> style="width: 100%; height: 42px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; cursor: pointer;">
                        </div>
                    </div>
                </div>

                <button type="submit" style="background: #38bdf8; color: #0f172a; font-weight: 900; padding: 0.9rem 2rem; border: none; border-radius: 0.75rem; font-size: 0.95rem; cursor: pointer; align-self: flex-start;">
                    Guardar y Aplicar Cambios
                </button>
            </form>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}
