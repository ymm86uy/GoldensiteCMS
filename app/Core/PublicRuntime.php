<?php
namespace App\Core;
use Modules\GoldensiteGdprBanner\GdprModule;

final class PublicRuntime
{
    public static ?array $editorial=null;
    public static function start(string $method, string $path): void
    {
        if ($method !== 'GET' || str_starts_with($path,'/forum/file/') || str_starts_with($path,'/forum/assets/') || str_starts_with($path,'/forum/brand/') || $path === GdprModule::POLICY || preg_match('~^/(?:admin|api|login|logout|register|registro|mi-cuenta|account|install\.php|download)(?:/|$)~', $path)) return;
        header('Cache-Control: no-store, private');
        if (FrontendEngine::isModuleActive('monitor-redirects')) {
            $row = Database::getInstance()->fetch('SELECT id, target_url, redirect_type FROM goldensite_redirects WHERE source_url = ?', [$path]);
            if ($row && in_array((int)$row['redirect_type'], [301, 302, 307, 308], true) && self::internal($row['target_url']) && parse_url($row['target_url'], PHP_URL_PATH) !== $path && !self::loop($path, $row['target_url'])) {
                Database::getInstance()->execute('UPDATE goldensite_redirects SET hits = hits + 1 WHERE id = ?', [$row['id']]);
                header('Location: ' . $row['target_url'], true, (int)$row['redirect_type']); exit;
            }
        }
        ob_start(static function (string $body) use ($path): string {
            $status = http_response_code();
            $isHtml = false;
            foreach (headers_list() as $header) if (stripos($header, 'Content-Type:') === 0) $isHtml = stripos($header, 'text/html') !== false;
            if (!$isHtml && !str_contains($body, '<html') && !str_contains($body, '<!DOCTYPE') && !str_contains($body, '<!doctype')) return $body;
            try {
                if ($status === 200 && Settings::get('use_ai_frontend', '0') !== '1') $body = ThemeService::decorate($body);
                if ($status === 200 && GdprModule::permitsOptional()) {
                    $extraHead=Settings::get('header_scripts'); $extraFooter=Settings::get('footer_scripts');
                    if ($extraHead!=='' && ($position=stripos($body,'</head>'))!==false) $body=substr_replace($body,$extraHead,$position,0);
                    if ($extraFooter!=='' && ($position=strripos($body,'</body>'))!==false) $body=substr_replace($body,$extraFooter,$position,0);
                }
                if ($status === 200 && FrontendEngine::isModuleActive('native-metrics')) AnalyticsTracker::track();
                if ($status === 200 && FrontendEngine::isModuleActive('seo-analytics') && ($head = stripos($body, '</head>')) !== false) $body = substr_replace($body, SeoTrackerEngine::renderHeadTags(), $head, 0);
                if ($status === 404 && FrontendEngine::isModuleActive('monitor-redirects') && RateLimiter::consume('404-log:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 60)) {
                    Database::getInstance()->execute("INSERT INTO goldensite_404_logs (url, ip_address, user_agent) VALUES (?, '', '')", [substr($path, 0, 255)]);
                }
                if ($status === 200 && FrontendEngine::isModuleActive('asset-optimizer')) {
                    $row = Database::getInstance()->fetch("SELECT setting_value FROM goldensite_optimizer_settings WHERE setting_key = 'minify_html'");
                    if (($row['setting_value'] ?? '0') === '1') $body=self::optimize($body);
                }
            } catch (\Throwable $e) { error_log('Public module runtime: ' . $e->getMessage()); }
            if (in_array($status,[200,404],true)) {
                $page=self::$editorial;
                if ($page) {
                    $seoTitle=trim((string)($page['meta_title']??''))?:($page['title']??''); $description=trim((string)($page['meta_description']??''))?:Settings::get('site_description');
                    $titleHtml=ConsoleView::escape($seoTitle.' · '.Settings::get('site_title','GoldenSiteCMS')); $body=preg_replace_callback('~<title\b[^>]*>.*?</title>~is',static fn()=>'<title>'.$titleHtml.'</title>',$body,1);
                    $metadata='<meta property="og:title" content="'.$titleHtml.'">'; if ($description!=='') $metadata.='<meta name="description" content="'.ConsoleView::escape($description).'"><meta property="og:description" content="'.ConsoleView::escape($description).'">';
                    if (($position=stripos($body,'</head>'))!==false) { $head=substr($body,0,$position); $head=preg_replace('~<meta\b(?=[^>]*(?:name|property)\s*=\s*["\x27](?:description|og:title|og:description)["\x27])[^>]*>~i','',$head); $body=$head.$metadata.substr($body,$position); }
                }
                $head=stripos($body,'</head>'); if ($head!==false) $body=substr_replace($body,Typography::style(false,$page?:null).SiteIcon::head(),$head,0);
                $body=preg_replace_callback('/<body\b([^>]*)>/i',static function($m) { return preg_match('/class=["\']/i',$m[1])?preg_replace('/class=(["\'])/i','class=$1nx-public ',$m[0],1):'<body class="nx-public"'.$m[1].'>'; },$body,1);
                if (stripos($body,'</body>')!==false) $body=str_ireplace('</body>',FrontendRuntime::cookieFooter().'<div style="padding:20px;display:flex;justify-content:center">'.Locale::selector().'</div></body>',$body);
                $body=Locale::html($body);
            }
            return $body;
        });
    }
    public static function internal(string $url): bool { return strlen($url) <= 255 && (bool)preg_match('~^/(?!/)[^\s\\\\]*$~D', $url); }
    private static function loop(string $source, string $target): bool
    {
        $seen = [$source => true];
        for ($i = 0; $i < 8; $i++) {
            $path = parse_url($target, PHP_URL_PATH);
            if (isset($seen[$path])) return true;
            $seen[$path] = true;
            $row = Database::getInstance()->fetch('SELECT target_url FROM goldensite_redirects WHERE source_url = ?', [$path]);
            if (!$row) return false;
            $target = $row['target_url'];
        }
        return true;
    }
    public static function optimize(string $html): string
    {
        // Preserve whitespace, scripts, styles and preformatted content exactly.
        return preg_replace_callback('~<(script|style|pre|textarea|code)\b[^>]*>.*?</\1\s*>|<!--(?!\[if|!|\s*\[).*?-->~is', static fn($m) => str_starts_with($m[0], '<!--') ? '' : $m[0], $html);
    }
}
