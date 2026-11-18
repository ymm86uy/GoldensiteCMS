<?php

namespace App\Core;

use App\Core\Database;

class PaywallEngine
{
    /**
     * Valida la licencia activa del plugin Goldensite Digital Vault & Paywall PRO
     */
    public static function isPaywallLicenseValid(): bool
    {
        return FrontendEngine::isModuleActive('digital-vault');
    }

    /**
     * Genera un enlace de descarga protegido firmado con HMAC-SHA256 y expiración
     */
    public static function generateSignedDownloadUrl(int $fileId, int $ttlSeconds = 3600): string
    {
        $secretKey = KeyStore::vault();
        $expires = time() + max(1, min($ttlSeconds, 86400));
        $signature = hash_hmac('sha256', "file={$fileId}&expires={$expires}", $secretKey);

        return "/download/{$fileId}?expires={$expires}&signature={$signature}";
    }

    /**
     * Valida si un enlace de descarga firmado es legítimo y no ha expirado
     */
    public static function validateSignedUrl(int $fileId, int $expires, string $signature): bool
    {
        if ($fileId <= 0 || time() >= $expires || $expires > time() + 86400) {
            return false; // Enlace expirado
        }

        $secretKey = KeyStore::vault();
        $expectedSignature = hash_hmac('sha256', "file={$fileId}&expires={$expires}", $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Procesador del Shortcode [goldensite_paywall]
     */
    public static function hasAccess(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if (($user['role'] ?? '') === 'admin' || ($user['role_id'] ?? 0) === 1) return true;
        try {
            $profile = Database::getInstance()->fetch('SELECT is_vip, vip_expires_at FROM goldensite_dating_profiles WHERE email = :email LIMIT 1', ['email' => $user['email']]);
            return (int) ($profile['is_vip'] ?? 0) === 1 && (empty($profile['vip_expires_at']) || strtotime($profile['vip_expires_at']) > time());
        } catch (\Throwable $error) { return false; }
    }
    public static function parse(string $content): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $pattern = '/\['.LegacyFormat::BRAND_PATTERN.'_paywall\](.*?)\[\/'.LegacyFormat::BRAND_PATTERN.'_paywall\]/s';

        return preg_replace_callback($pattern, function ($matches) {
            $protectedContent = $matches[1] ?? '';

            // 1. Verificar si el módulo PRO está licenciado
            if (!self::isPaywallLicenseValid()) {
                return '
                <div class="my-6 p-6 rounded-2xl bg-slate-900/90 border border-amber-500/40 text-center space-y-2">
                    <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">🔒 Módulo Requerido</span>
                    <p class="text-xs text-slate-300">El contenido protegido requiere la activación del plugin <strong>Goldensite Digital Vault & Paywall PRO</strong>.</p>
                </div>';
            }

            // 2. Verificar si el usuario tiene sesión activa de suscriptor
            $userLoggedIn = self::hasAccess();

            if ($userLoggedIn) {
                return '
                <div class="my-6 p-6 rounded-2xl bg-slate-900/90 border border-emerald-500/30 text-slate-200 shadow-neon-purple">
                    <div class="flex items-center gap-2 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2">
                        <span>🔓 Contenido VIP Desbloqueado (Vault PRO)</span>
                    </div>
                    <div>' . $protectedContent . '</div>
                </div>';
            }

            return '
            <div class="my-6 p-8 rounded-2xl bg-slate-900/90 border border-purple-500/40 text-center space-y-4 shadow-2xl backdrop-blur-md">
                <div class="text-3xl">🛡️</div>
                <div class="space-y-1">
                    <h4 class="text-base font-black text-white">Goldensite Digital Vault & Paywall PRO</h4>
                    <p class="text-slate-400 text-xs">Acceso exclusivo a descargas con enlaces temporales firmados con HMAC.</p>
                </div>
                <div class="pt-2 flex justify-center gap-3">
                    <a href="/mi-cuenta" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-purple-600 text-slate-950 font-black text-xs uppercase tracking-wider hover:opacity-90 transition-opacity">
                        Iniciar Sesión / Suscribirme
                    </a>
                </div>
            </div>';
        }, $content);
    }
}
