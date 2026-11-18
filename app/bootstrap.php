<?php

require_once __DIR__.'/Core/LegacyFormat.php';

if (PHP_SAPI !== 'cli' && \App\Core\LegacyFormat::updateInProgress(dirname(__DIR__))) {
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 120');
    exit('GoldenSiteCMS se está actualizando. Vuelve a intentarlo en unos minutos.');
}

require_once __DIR__ . '/../vendor/autoload.php';

\App\Core\Environment::load(\App\Core\Environment::get('GOLDENSITE_ENV_FILE', dirname(__DIR__) . '/.env'));
\App\Core\ForumDistribution::register();
\App\Core\Session::start();
if (\App\Core\Installer::installed()) {
    $siteTimezone=\App\Core\Settings::get('site_timezone','America/Montevideo');
    if (in_array($siteTimezone,timezone_identifiers_list(),true)) date_default_timezone_set($siteTimezone);
}

if (PHP_SAPI !== 'cli') {
    ob_start(static function(string $html): string { if (stripos($html,'</head>')===false) return $html; if (!str_contains($html,'id="nx-a11y-config"')&&str_contains($html,'</body>')&&!preg_match('~^/(?:login|account)(?:/|$)~',parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))) { $html=str_replace('id="contenido-principal"','id="nx-main-content" tabindex="-1"',$html); $html=preg_replace('/<main\b(?![^>]*\bid=)/i','<main id="nx-main-content" tabindex="-1"',$html,1); $html=str_ireplace('</body>',\App\Core\Accessibility::html().'</body>',$html); } if (!str_contains($html,'id="goldensite-site-icon"')) $html=str_ireplace('</head>',\App\Core\SiteIcon::head().'</head>',$html); if (preg_match('~^/login(?:/|$)~',parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))) { if (\App\Core\Settings::get('oidc_enabled')==='1'&&str_contains($html,'</body>')) $html=str_ireplace('</body>','<p style="text-align:center;padding:16px"><a href="/login/enterprise" style="color:#c5ef69">'.\App\Core\Locale::text('Acceso empresarial').'</a></p></body>',$html); $html=\App\Core\Locale::html($html); } return $html; });
    ini_set('display_errors', '0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Keep existing inline and HTTPS integrations while restricting plugins, framing and script evaluation.
    header("Content-Security-Policy: script-src 'self' https: 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
    if (in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true)
        || ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if (\App\Core\Installer::installed() && \App\Core\Settings::get('enable_security_headers','1')==='1') {
        header('Cross-Origin-Opener-Policy: same-origin'); header('X-Permitted-Cross-Domain-Policies: none'); header('Origin-Agent-Cluster: ?1');
    }
    set_exception_handler(static function (Throwable $error): void {
        error_log(get_class($error) . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo completar la solicitud. Consulta el registro del servidor.';
    });
    if (\App\Core\Installer::installed()) \App\Middleware\WafMiddleware::inspect();
    \App\Security\UploadAntivirus::inspect($_FILES);
}
