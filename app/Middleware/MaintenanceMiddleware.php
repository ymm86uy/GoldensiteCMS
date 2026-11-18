<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Settings;
use App\Core\SiteConfiguration;

class MaintenanceMiddleware
{
    /**
     * Evalúa si el modo mantenimiento está activo y bloquea el acceso público.
     */
    public static function handle(): void
    {
        // 1. Si no está activo en la configuración, permitir acceso libre
        if (!SiteConfiguration::maintenanceEnabled()) {
            return;
        }

        // 2. Verificar si el usuario actual es administrador (role_id = 1 o role = 'admin')
        $isAdmin = Auth::check() && \App\Core\IdentityAccess::canEnter();

        if ($isAdmin) {
            return;
        }

        // 3. Excepciones de rutas para permitir el inicio de sesión del administrador
        $uri = \App\Core\AdminPath::normalize(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $allowedRoutes = ['/login', '/login/2fa', '/login/enterprise', '/account/security', '/logout', '/registro', '/register', \Modules\GoldensiteGdprBanner\GdprModule::POLICY];

        if (in_array($uri, $allowedRoutes) || ($uri==='/admin'||str_starts_with($uri, '/admin/'))) {
            return;
        }

        http_response_code(503); header('Cache-Control: no-store'); header('Retry-After: '.max(60,min(86400,(int)Settings::get('maintenance_retry_after','3600'))));
        \App\Core\MaintenancePage::render(); exit;
    }
}
