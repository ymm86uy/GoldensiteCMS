<?php

namespace Modules\GoldensiteGdprBanner;

use App\Core\{Csrf, Database, FrontendEngine, PublicRuntime, RequestGuard, Settings};

class GdprModule
{
    private const COOKIE = 'goldensite_gdpr_consent';
    public const POLICY = '/politica-cookies';
    private static ?array $settings = null;

    public static function settings(): array
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                foreach (Database::getInstance()->fetchAll('SELECT setting_key, setting_value FROM goldensite_gdpr_settings') as $row) {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Throwable $error) {
                error_log('Preferencias de cookies: '.$error->getMessage());
            }
        }
        return self::$settings;
    }

    public static function enabled(): bool
    {
        return FrontendEngine::isModuleActive('gdpr-banner') && (self::settings()['gdpr_enabled'] ?? '1') === '1';
    }

    public static function choice(): ?string
    {
        $choice = $_COOKIE[self::COOKIE] ?? null;
        return in_array($choice, ['all', 'essential'], true) ? $choice : null;
    }

    public static function permitsOptional(): bool
    {
        return self::choice() === 'all' && self::enabled();
    }

    public function saveConsent(): void
    {
        RequestGuard::post();
        $choice = $_POST['choice'] ?? null;
        if (!in_array($choice, ['all', 'essential'], true) || ($choice === 'all' && !self::enabled())) {
            http_response_code(422);
            return;
        }
        setcookie(self::COOKIE, $choice, [
            'expires' => time() + 180 * 86400,
            'path' => '/',
            'secure' => session_get_cookie_params()['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Cache-Control: no-store, private');
        $returnTo = $_POST['return_to'] ?? '';
        header('Location: '.(is_string($returnTo) && PublicRuntime::internal($returnTo) ? $returnTo : self::POLICY), true, 303);
    }

    public function policy(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, private');
        $settings = self::settings();
        $siteTitle = Settings::get('site_title', 'GoldenSiteCMS');
        $responsible = ($settings['policy_responsible'] ?? '') ?: $siteTitle;
        $address = $settings['policy_address'] ?? '';
        $email = $settings['policy_email'] ?? '';
        $providers = $settings['policy_providers'] ?? '';
        $choice = self::choice();
        require dirname(__DIR__, 2).'/views/cookie-policy.php';
    }

    public static function renderFrontend(): void
    {
        if (!self::enabled() || self::choice() !== null) return;
        $settings = self::settings();
        $position = $settings['position'] ?? 'bottom-right';
        if (!in_array($position, ['bottom-right', 'bottom-left', 'top-bar'], true)) $position = 'bottom-right';
        $color = $settings['theme_color'] ?? '#bddd79';
        if (!preg_match('/^#[a-f0-9]{6}$/iD', $color)) $color = '#bddd79';
        $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
        ?>
        <section id="goldensite-gdpr-banner" data-position="<?= $position ?>" style="--cookie-accent:<?= $color ?>" aria-labelledby="goldensite-cookie-title">
            <h2 id="goldensite-cookie-title"><?= htmlspecialchars(($settings['banner_title'] ?? '') ?: 'Tu privacidad y las cookies') ?></h2>
            <p><?= htmlspecialchars(($settings['banner_text'] ?? '') ?: 'Usamos cookies necesarias para que el sitio funcione. Tú decides si permites también los servicios opcionales.') ?></p>
            <p><a href="<?= self::POLICY ?>">Leer la política de cookies</a></p>
            <?php self::renderChoices($returnTo, $settings); ?>
        </section>
        <?php
    }

    public static function renderChoices(string $returnTo, array $labels = []): void
    {
        ?>
        <form class="goldensite-cookie-choices" action="<?= self::POLICY ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <?php if (self::enabled()): ?>
            <button type="submit" name="choice" value="all"><?= htmlspecialchars(($labels['accept_btn_text'] ?? '') ?: 'Aceptar opcionales') ?></button>
            <?php endif; ?>
            <button type="submit" name="choice" value="essential"><?= htmlspecialchars(($labels['reject_btn_text'] ?? '') ?: 'Usar solo necesarias') ?></button>
        </form>
        <?php
    }

    public function renderAdmin(): void { (new \App\Controllers\Admin\GdprController())->index(); }
}
