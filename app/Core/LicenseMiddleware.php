<?php

namespace App\Core;

use App\Core\LicenseEngine;

class LicenseMiddleware
{
    /**
     * Verifica la licencia de un módulo específico.
     * Aplica la misma comprobación en desarrollo y producción.
     */
    public static function enforce(string $moduleKey, string $moduleName, string $priceInfo): void
    {
        $moduleName=ModuleCatalog::get($moduleKey)['name']??$moduleName;
        $priceInfo = ModuleCatalog::price($moduleKey);
        // Si el motor detecta la licencia activa, permite el paso directo
        if (LicenseEngine::isLicenseActive($moduleKey)) {
            return;
        }

        // Si se encuentra en servidor de producción y no está activa, bloquea la solicitud
        http_response_code(403);
        if (Auth::check()&&IdentityAccess::canEnter()) { ConsoleView::render('license_required',['moduleName'=>$moduleName,'price'=>$priceInfo,'issuable'=>ModuleCatalog::issuable($moduleKey)],'Disponibilidad del módulo · Goldensite'); } else { $e=ConsoleView::escape(...); $siteTitle='Licencia requerida · Goldensite'; $content='<section style="padding:40px;border:1px solid var(--primary);border-radius:18px"><h1>Contenido no disponible</h1><p>'.$e($moduleName).'</p><a href="/contacto">Contactar con administración</a></section>'; require dirname(__DIR__,2).'/views/layouts/main.php'; }
        exit;
    }
}
