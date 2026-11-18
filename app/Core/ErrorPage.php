<?php
namespace App\Core;
final class ErrorPage
{
    public static function notFound(): void { http_response_code(404); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex'); $e=ConsoleView::escape(...); $siteTitle='404 · '.Settings::get('site_title','GoldenSiteCMS'); $activeTheme=ThemeService::active(); ob_start(); require dirname(__DIR__,2).'/views/errors/not_found_content.php'; $content=ob_get_clean(); $layout=dirname(__DIR__,2).'/views/themes/'.$activeTheme.'/layout.php'; require is_file($layout)?$layout:dirname(__DIR__,2).'/views/layouts/main.php'; }
}
