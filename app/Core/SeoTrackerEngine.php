<?php

namespace App\Core;

use Modules\GoldensiteGdprBanner\GdprModule;

class SeoTrackerEngine
{
    /**
     * Valida si la licencia del plugin SEO & Analytics Tracker PRO está activa
     */
    public static function isLicenseValid(): bool
    {
        return FrontendEngine::isModuleActive('seo-analytics');
    }

    /**
     * Inyecta Metadatos OpenGraph, Twitter Cards, Canonical y Scripts en el <head>
     */
    public static function renderHeadTags(string $pageTitle = '', string $description = '', string $ogImage = ''): string
    {
        if (!self::isLicenseValid()) {
            return "<!-- Goldensite SEO & Analytics Tracker PRO: Licencia inactiva o no registrada -->\n";
        }

        $siteSettings = FrontendEngine::getSettings();
        $siteName     = $siteSettings['site_title'] ?? 'GoldenSiteCMS';
        $defaultDesc = $siteSettings['site_description'] ?? 'Sitio desarrollado con GoldenSiteCMS Engine.';
        
        $finalTitle = !empty($pageTitle) ? $pageTitle : $siteName;
        $finalDesc  = !empty($description) ? $description : $defaultDesc;

        $currentUrl = rtrim(Environment::get('APP_URL'), '/') . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        $gaId       = Settings::get('seo_ga_id', ''); // ID de Google Analytics (ej. G-XXXXXXX)
        $customCode = Settings::get('seo_header_scripts', ''); // Scripts personalizados en header

        $html = "\n<!-- Goldensite SEO & Analytics Tracker PRO Injections -->\n";
        $html .= "<link rel=\"canonical\" href=\"" . htmlspecialchars($currentUrl) . "\" />\n";
        $html .= "<meta name=\"description\" content=\"" . htmlspecialchars($finalDesc) . "\" />\n";

        // Open Graph
        $html .= "<meta property=\"og:title\" content=\"" . htmlspecialchars($finalTitle) . "\" />\n";
        $html .= "<meta property=\"og:description\" content=\"" . htmlspecialchars($finalDesc) . "\" />\n";
        $html .= "<meta property=\"og:type\" content=\"website\" />\n";
        $html .= "<meta property=\"og:url\" content=\"" . htmlspecialchars($currentUrl) . "\" />\n";
        if (!empty($ogImage)) {
            $html .= "<meta property=\"og:image\" content=\"" . htmlspecialchars($ogImage) . "\" />\n";
        }

        // Twitter Cards
        $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
        $html .= "<meta name=\"twitter:title\" content=\"" . htmlspecialchars($finalTitle) . "\" />\n";
        $html .= "<meta name=\"twitter:description\" content=\"" . htmlspecialchars($finalDesc) . "\" />\n";

        // Google Analytics Script
        $optional = GdprModule::permitsOptional();
        if ($optional && preg_match('/^(?:G|UA)-[A-Z0-9-]{4,40}$/D', $gaId)) {
            $html .= "<!-- Global site tag (gtag.js) - Google Analytics -->\n";
            $html .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($gaId) . "\"></script>\n";
            $html .= "<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', '" . htmlspecialchars($gaId) . "');\n</script>\n";
        }

        // Scripts Personalizados del Administrador
        if ($optional && $customCode !== '') {
            $html .= $customCode . "\n";
        }

        $html .= "<!-- End Goldensite SEO & Analytics Tracker PRO -->\n\n";

        return $html;
    }
}
