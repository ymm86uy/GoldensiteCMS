<?php
namespace App\Core;

/** Public-key verification works on client sites without the issuing database. */
final class LicenseCode
{
    private static function encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function decode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) throw new \InvalidArgumentException('Código de licencia inválido.');
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) throw new \InvalidArgumentException('Código de licencia inválido.');
        return $decoded;
    }
    public static function domain(string $value): string
    {
        $value = strtolower(trim($value));
        $parts = parse_url(str_contains($value, '://') ? $value : 'https://' . $value);
        if (!$parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !in_array($parts['path'] ?? '', ['', '/'], true)) throw new \InvalidArgumentException('Introduce solo un dominio o dirección IP, sin rutas.');
        $host = trim($parts['host'], '[]');
        if (strlen($host) > 150 || ($host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) throw new \InvalidArgumentException('Dominio inválido.');
        return $host;
    }
    public static function siteDomain(): string
    {
        $url = Environment::get('APP_URL');
        if ($url === '') throw new \InvalidArgumentException('Configura APP_URL con el dominio real del sitio antes de activar licencias.');
        return self::domain($url);
    }
    public static function sign(array $claims, string $privateKey, string $prefix = 'NFX1'): string
    {
        $payload = self::encode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!openssl_sign($prefix . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) throw new \RuntimeException('No se pudo firmar la licencia.');
        return $prefix . '.' . $payload . '.' . self::encode($signature);
    }
    public static function verify(string $code, string $module, string $domain): array
    {
        $claims = self::open($code, 'NFX1', 2048);
        if (!is_array($claims) || ($claims['v'] ?? null) !== 1 || !is_string($claims['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $claims['id'])
            || ($claims['m'] ?? null) !== ModuleCatalog::canonical($module) || !ModuleCatalog::isPro($module)
            || ($claims['d'] ?? null) !== self::domain($domain) || !is_int($claims['iat'] ?? null) || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > time() + 120 || $claims['exp'] <= time() || $claims['exp'] <= $claims['iat']) throw new \InvalidArgumentException('Licencia caducada o emitida para otro módulo o dominio.');
        return $claims;
    }
    public static function open(string $code, string $prefix, int $limit): array
    {
        if (strlen($code) > $limit || count($parts = explode('.', $code)) !== 3 || $parts[0] !== $prefix) throw new \InvalidArgumentException('Código firmado inválido.');
        $publicFile = dirname(__DIR__, 2) . '/config/license-public.pem';
        if (!is_file($publicFile) || openssl_verify($parts[0] . '.' . $parts[1], self::decode($parts[2]), file_get_contents($publicFile), OPENSSL_ALGO_SHA256) !== 1) throw new \InvalidArgumentException('Firma de licencia inválida.');
        $claims = json_decode(self::decode($parts[1]), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($claims)) throw new \InvalidArgumentException('Contenido firmado inválido.');
        return $claims;
    }
}
