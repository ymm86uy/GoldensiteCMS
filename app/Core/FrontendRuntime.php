<?php
namespace App\Core;

use Modules\GoldensiteGdprBanner\GdprModule;

final class FrontendRuntime
{
    private static bool $rendered = false;
    private static bool $cookiesRendered = false;

    public static function footer(): string
    {
        if (self::$rendered) return '';
        self::$rendered = true;
        ob_start();
        if (FrontendEngine::isModuleActive('newsletter')) {
            $notice = $_SESSION['newsletter_status'] ?? null; unset($_SESSION['newsletter_status']);
            if (is_array($notice) && isset($notice['msg'])) echo '<p role="status">' . htmlspecialchars($notice['msg'], ENT_QUOTES, 'UTF-8') . '</p>';
            echo \Plugins\GoldensiteNewsletter\MailerEngine::renderSubscriptionWidget();
        }
        if (FrontendEngine::isModuleActive('live-chat')&&(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)!=='/chat')) echo '<a href="/chat" class="nx-community-trigger">Chat de la comunidad</a>';
        if (FrontendEngine::isModuleActive('forum')) echo '<p><a href="/forum">Foro de la comunidad</a></p>';
        if (FrontendEngine::isModuleActive('social-network')) echo '<p><a href="/social">Red social</a></p>';
        echo self::cookieFooter();
        return ob_get_clean();
    }

    public static function cookieFooter(): string
    {
        if (self::$cookiesRendered || (ProductEdition::current() !== 'commercial' && !GdprModule::enabled())) return '';
        self::$cookiesRendered = true;
        ob_start();
        ?>
        <div style="padding:.65rem 1rem;text-align:center"><a style="color:inherit;text-decoration:underline" href="<?= GdprModule::POLICY ?>">Política y preferencias de cookies</a></div>
        <?php if (GdprModule::enabled() && GdprModule::choice() === null): ?>
        <style>
            #goldensite-gdpr-banner{position:fixed;right:1rem;bottom:4.5rem;z-index:99999;box-sizing:border-box;width:min(28rem,calc(100% - 2rem));max-height:calc(100vh - 5.5rem);overflow:auto;padding:1rem 1.25rem;background:#142018;color:#f5f7f1;border:1px solid var(--cookie-accent);border-radius:.8rem;box-shadow:0 10px 32px #0008;text-align:left;font:1rem/1.5 system-ui,sans-serif}
            #goldensite-gdpr-banner[data-position="bottom-left"]{left:1rem;right:auto}
            #goldensite-gdpr-banner[data-position="top-bar"]{top:1rem;bottom:auto}
            #goldensite-gdpr-banner h2{color:inherit;font:700 1.2rem/1.2 system-ui,sans-serif;margin:0}
            #goldensite-gdpr-banner p{color:inherit;margin:.6rem 0}
            #goldensite-gdpr-banner a{color:#f5f7f1;text-decoration:underline}
            #goldensite-gdpr-banner form{display:flex;flex-wrap:wrap;gap:.55rem}
            #goldensite-gdpr-banner button{flex:1 1 10rem;min-height:44px;padding:.55rem .65rem;color:#f5f7f1;background:#283b2c;border:1px solid #a6bca9;border-radius:.4rem;font:600 .9rem/1.2 system-ui,sans-serif;cursor:pointer}
            #goldensite-gdpr-banner :focus-visible{outline:3px solid #d5eb7b;outline-offset:2px}
        </style>
        <?php GdprModule::renderFrontend(); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public static function decorate(string $html): string
    {
        $footer = self::footer();
        $position = strripos($html, '</body>');
        return $position === false ? $html . $footer : substr_replace($html, $footer, $position, 0);
    }
}
