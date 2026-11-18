<?php

namespace App\Core;

use App\Core\Database;

class FrontendEngine
{
    private static ?array $moduleStates = null;
    public static function resetModuleCache(): void { self::$moduleStates = null; }
    public static function getSettings(): array
    {
        $settings = [];
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    if ($row['setting_key'] === 'active_theme' && !preg_match('/^[A-Za-z0-9_-]+$/D', (string) $row['setting_value'])) $row['setting_value'] = 'dark-cyber';
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {}

        return $settings;
    }

    public static function isModuleActive(string $moduleId): bool
    {
        try {
            $canonical = ModuleCatalog::canonical($moduleId);
            if ($canonical === 'edge-waf') return true;
            if (!ModuleCatalog::get($canonical)) return false;
            if (self::$moduleStates === null) {
                $rows = Database::getInstance()->fetchAll('SELECT module_id, is_active FROM goldensite_active_modules');
                self::$moduleStates = []; $exact = [];
                foreach ($rows as $row) {
                    $id = ModuleCatalog::canonical($row['module_id']);
                    if ($row['module_id'] === $id) { self::$moduleStates[$id] = (bool)$row['is_active']; $exact[$id] = true; }
                    elseif (!isset($exact[$id])) self::$moduleStates[$id] = (bool)$row['is_active'];
                }
            }
            $defaultActive = $canonical === 'gdpr-banner' && ProductEdition::current() === 'commercial';
            return (self::$moduleStates[$canonical] ?? $defaultActive) && (!ModuleCatalog::isPro($canonical) || LicenseEngine::isLicenseActive($canonical));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function renderShortcodes(string $content): string
    {
        $content = preg_replace_callback('~<p>\s*\[goldensite_weather_time\]\s*</p>~i', static fn()=>WeatherTime::widget(), $content);
        $content = preg_replace_callback('~<p>\s*\[goldensite_ads\]\s*</p>~i', static fn()=>Ads::render('inline', parse_url($_SERVER['REQUEST_URI']??'/', PHP_URL_PATH) ?: '/'), $content);
        $content=preg_replace_callback('/\['.LegacyFormat::BRAND_PATTERN.'_form\s+id="(\d+)"\]/',static fn($m)=>FormBuilder::shortcode((int)$m[1]),$content);
        // Shortcode: [goldensite_gallery id="X"] con Lightbox Modal Integrado
        $content = preg_replace_callback('/\[goldensite_gallery\s+id="(\d+)"\]/', function($matches) {
            if (!self::isModuleActive('goldensite-gallery')) {
                return '<div style="color:#ef4444; padding:1rem; border:1px dashed #ef4444; border-radius:8px; font-size:0.85rem;">⚠️ El módulo "Goldensite Ultra Gallery" está desactivado. Actívalo en el panel de administración.</div>';
            }

            $galleryId = (int)$matches[1];
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM goldensite_galleries WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $galleryId]);
                $gal = $stmt->fetch();

                if ($gal) {
                    $images = json_decode($gal['images_json'], true) ?? [];
                    if ($gal['layout_type'] === 'carousel') return GalleryCarousel::render($gal, $images);
                    $html = '<div class="goldensite-gallery-container grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 my-8">';
                    foreach ($images as $imgUrl) {
                        if (!is_string($imgUrl) || !preg_match('~^(?:/(?!/)|https://)~i', $imgUrl)) continue;
                        $html .= '<div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900 cursor-pointer group" data-image="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '" onclick="openLightbox(this.dataset.image)">';
                        $html .= '<img src="' . htmlspecialchars($imgUrl) . '" class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-110">';
                        $html .= '</div>';
                    }
                    $html .= '</div>';

                    // Modal Lightbox
                    $html .= '
                    <div id="goldensiteLightbox" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md hidden flex items-center justify-center p-4" onclick="closeLightbox()">
                        <div class="relative max-w-5xl w-full text-center" onclick="event.stopPropagation()">
                            <img id="lightboxImg" src="" class="max-h-[85vh] mx-auto rounded-xl shadow-2xl border border-slate-700/50">
                            <button onclick="closeLightbox()" class="absolute -top-12 right-0 text-white font-bold text-2xl hover:text-sky-400">✕ Cerrar</button>
                        </div>
                    </div>
                    <script>
                        function openLightbox(url) {
                            document.getElementById("lightboxImg").src = url;
                            document.getElementById("goldensiteLightbox").classList.remove("hidden");
                        }
                        function closeLightbox() {
                            document.getElementById("goldensiteLightbox").classList.add("hidden");
                        }
                    </script>';
                    return $html;
                }
            } catch (\Throwable $e) {}
            return '';
        }, $content);

        // The same delivery service enforces permissions for embedded resources.
        $content = preg_replace_callback('/\['.LegacyFormat::BRAND_PATTERN.'_download\s+id="(\d+)"(?:\s+link="([a-f0-9]{32})")?\]/', static fn($match) => DownloadLibrary::shortcode((int)$match[1],$match[2]??''),$content);

        return $content;
    }
}
