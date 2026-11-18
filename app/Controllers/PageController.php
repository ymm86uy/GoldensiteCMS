<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\FrontendEngine;
use App\Core\PaywallEngine;
use App\Core\Settings;

class PageController
{
    public function show(string $slug): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        header('Cache-Control: private, no-store');

        $db = Database::getInstance()->getConnection();
        $page = null;
        $isDynamicPage = false;

        try {
            $stmtP = $db->prepare("SELECT * FROM goldensite_pages WHERE slug = :slug AND status = 'published' AND is_published = 1 LIMIT 1");
            $stmtP->execute(['slug' => $slug]);
            $page = $stmtP->fetch(\PDO::FETCH_ASSOC);
            if ($page) $isDynamicPage = true;
        } catch (\Throwable $e) {}

        if (!$page) {
            try {
                $page = \App\Models\Post::findBySlug($slug);
            } catch (\Throwable $e) {}
        }

        if ($page) {
            $translations=json_decode($page['translations_json']??'{}',true); $language=\App\Core\Locale::language(); if (is_array($translations[$language]??null)) foreach (['title','content'] as $key) if (is_string($translations[$language][$key]??null) && $translations[$language][$key]!=='') $page[$key]=$translations[$language][$key];
            \App\Core\PublicRuntime::$editorial=$page;
            $siteSettings    = FrontendEngine::getSettings();
            $activeTheme     = Settings::get('active_theme', $siteSettings['active_theme'] ?? 'default');
            $siteTitle       = !empty($page['meta_title']) ? $page['meta_title'] : htmlspecialchars($page['title']) . " - " . ($siteSettings['site_title'] ?? 'GoldenSiteCMS');
            $metaDescription = !empty($page['meta_description']) ? htmlspecialchars($page['meta_description']) : 'Página oficial de GoldenSiteCMS.';
            $ogImage         = !empty($page['og_image']) ? $page['og_image'] : '';

            $contentRaw = $page['content'] ?? '';
            $processedContent = FrontendEngine::renderShortcodes(PaywallEngine::parse($contentRaw));

            ob_start();
            ?>
            <article data-nx-content class="nx-editorial max-w-5xl mx-auto px-6 py-12 space-y-10" style="color: #fff; padding: 2.5rem 1.5rem;">
                <div class="prose prose-invert max-w-none text-slate-300 text-sm leading-relaxed space-y-4">
                    <?= $processedContent ?>
                </div>
            </article>
            <?php
            $content = ob_get_clean();

            ob_start();
            $themeLayoutPath = __DIR__ . "/../../views/themes/{$activeTheme}/layout.php";
            if (file_exists($themeLayoutPath)) {
                require_once $themeLayoutPath;
            } else {
                require_once __DIR__ . '/../../views/layouts/main.php';
            }
            $finalOutput = ob_get_clean();

            echo $finalOutput;
        } else {
            \App\Core\ErrorPage::notFound();
        }
    }
}
