<?php
namespace App\Core;

final class WeatherTime
{
    public const ID = 'weather-time';
    public const SHORTCODE = '[goldensite_weather_time]';
    public const POSITIONS = ['top'=>'Inicio del contenido','bottom'=>'Final del contenido','shortcode'=>'Dentro de páginas (código corto)'];

    public static function providerKey(): string
    {
        return trim(Environment::get('OPENWEATHER_API_KEY'));
    }

    public static function provider(): ?string
    {
        if (Settings::get('weather_met_enabled') === '1' && self::validContact(Settings::get('weather_met_contact'))) return 'met';
        return self::providerKey() !== '' ? 'openweather' : null;
    }

    public static function validContact(string $url): bool
    {
        $parts = parse_url($url);
        return strlen($url)<=240 && str_starts_with($url,'https://') && filter_var($url,FILTER_VALIDATE_URL)!==false
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]+)?(?:/[A-Za-z0-9._/-]*)?$~D',$url)===1;
    }

    public static function widget(): string
    {
        if (!FrontendEngine::isModuleActive(self::ID)) return '';
        $provider = self::provider();
        ob_start();
        require dirname(__DIR__, 2).'/views/partials/weather_time.php';
        return ob_get_clean();
    }

    public static function decorate(string $html, string $widget): string
    {
        if ((http_response_code() ?: 200) !== 200 || !str_contains($html, '</body>')) return $html;
        $position = Settings::get('weather_time_position', 'shortcode');
        if (!str_contains($html, 'data-gs-weather-widget') && $position !== 'shortcode') {
            if ($position === 'top' && (preg_match('~<main\b[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE)
                || preg_match('~<body\b[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE))) {
                $offset = $match[0][1] + strlen($match[0][0]);
            } else {
                $offset = strripos($html, '</main>');
                if ($offset === false) $offset = strripos($html, '</body>');
            }
            $html = substr_replace($html, $widget, $offset, 0);
        }
        if (!str_contains($html, 'data-gs-weather-widget')) return $html;
        if (self::provider() !== null) {
            header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
            header('Cache-Control: no-store, private');
        }
        $assets = '<link rel="stylesheet" href="/assets/weather-time.css"><script src="/assets/weather-time.js" defer></script>';
        $head = stripos($html, '</head>');
        return $head === false ? $html : substr_replace($html, $assets, $head, 0);
    }

    public static function coordinates(array $input): ?array
    {
        $result = [];
        foreach (['lat'=>90, 'lon'=>180] as $name=>$max) {
            $value = $input[$name] ?? null;
            if (!is_string($value) || !preg_match('/^-?\d{1,3}(?:\.\d{1,2})?$/D', $value) || abs((float)$value)>$max) return null;
            $result[$name] = number_format((float)$value, 1, '.', '');
        }
        return $result;
    }

    public static function current(array $coordinates, string $key): array
    {
        $query = http_build_query($coordinates + ['units'=>'metric','lang'=>'es','appid'=>$key], '', '&', PHP_QUERY_RFC3986);
        $curl = curl_init('https://api.openweathermap.org/data/2.5/weather?'.$query);
        $body = '';
        curl_setopt_array($curl, [CURLOPT_PROTOCOLS_STR=>'https', CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT_MS=>1500, CURLOPT_TIMEOUT_MS=>4000, CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>16384) return 0;
                $body .= $chunk;
                return strlen($chunk);
            }]);
        if (curl_exec($curl) !== true || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) throw new \RuntimeException('Proveedor meteorológico no disponible.');
        $json = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        $temperature = $json['main']['temp'] ?? null;
        $condition = $json['weather'][0]['description'] ?? null;
        if ((!is_float($temperature) && !is_int($temperature)) || !is_finite((float)$temperature) || abs($temperature)>100
            || !is_string($condition) || $condition === '' || mb_strlen($condition)>80) throw new \RuntimeException('Respuesta meteorológica inválida.');
        return ['temperature'=>round($temperature,1),'condition'=>$condition];
    }
}
