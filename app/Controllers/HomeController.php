<?php

namespace App\Controllers;

class HomeController
{
    public function index(): void
    {
        header('Cache-Control: private, no-store');
        if (\App\Core\Settings::get('use_ai_frontend', '0') === '1') {
            $custom = \App\Core\Settings::get('custom_ai_frontend', '');
            if ($custom !== '') {
                if (\App\Core\Settings::get('builder_content_policy')==='restricted') {
                    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; object-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
                }
                echo \App\Core\FrontendRuntime::decorate(\App\Core\FrontendEngine::renderShortcodes(\App\Core\PaywallEngine::parse($custom)));
                return;
            }
        }
        $viewFile = __DIR__ . '/../../views/frontend/home.php';
        if (!file_exists($viewFile)) {
            $viewFile = __DIR__ . '/../../views/home.php';
        }

        require_once $viewFile;
    }
}
