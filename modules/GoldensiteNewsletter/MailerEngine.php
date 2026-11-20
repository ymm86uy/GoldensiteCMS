<?php

namespace Plugins\GoldensiteNewsletter;

use App\Core\Database;

class MailerEngine
{
    public static function initTable(): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            \App\Core\SchemaMigrator::migrate($db,null,['goldensite_subscribers']);
        } catch (\Throwable $e) {
            error_log('Goldensite newsletter schema: '.$e->getMessage());
            throw new \RuntimeException('El boletín no está disponible temporalmente.',0,$e);
        }
    }

    public static function subscribe(string $email,bool $consent=false): bool
    {
        try { \App\Core\NewsletterService::subscribe($email,$consent); return true; }
        catch (\InvalidArgumentException $e) { return false; }
    }

    public static function renderSubscriptionWidget(): string
    {
        if (!\App\Core\FrontendEngine::isModuleActive('newsletter')) return '';
        return '
        <div id="newsletter" style="background:var(--card-bg,#1e293b); border:1px solid #334155; border-radius:8px; padding:1.5rem; margin:1.5rem 0;">
            <h4 style="color:var(--text-color,#f8fafc); margin-bottom:0.5rem;">📩 Suscríbete a nuestro boletín</h4>
            <p style="color:var(--text-color,#94a3b8); font-size:0.875rem; margin-bottom:1rem;">Recibe las últimas publicaciones directamente en tu bandeja de entrada.</p>
            <form action="/newsletter/subscribe" method="POST" style="display:flex;flex-wrap:wrap;gap:12px;">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') . '">
                <input type="email" name="email" placeholder="tu@email.com" required style="flex:1; padding:0.5rem 0.75rem; border-radius:4px; border:1px solid #475569; background:#0f172a; color:#fff;">
                <label style="width:100%;display:flex;gap:10px;align-items:center"><input type="checkbox" name="consent" value="1" required> Acepto recibir el boletín y confirmar mi dirección por correo.</label>
                <button type="submit" style="background:var(--primary,#38bdf8); color:#0f172a; border:none; padding:0.5rem 1rem; font-weight:bold; border-radius:4px; cursor:pointer;">Suscribirme</button>
            </form>
        </div>';
    }
}
