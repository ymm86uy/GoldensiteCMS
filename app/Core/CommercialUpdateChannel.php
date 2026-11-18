<?php
namespace App\Core;

final class CommercialUpdateChannel
{
    public const BASE = 'https://goldensitecms.com/updates/commercial/';
    public const SIGNATURE_PREFIX = 'NFXF1';
    private const MAX_FEED_BYTES = 4096;

    public static function check(): ?array
    {
        if (ProductEdition::current() !== 'commercial') {
            throw new \LogicException('Este canal solo distribuye actualizaciones comerciales.');
        }
        $from = ReleaseUpdate::version();
        $body = '';
        $curl = curl_init(self::BASE.$from.'.nfx');
        SafeHttp::useSystemTrust($curl);
        try {
            curl_setopt_array($curl, [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (curl_getinfo($handle, CURLINFO_RESPONSE_CODE) !== 200) return 0;
                    if (strlen($body) + strlen($chunk) > self::MAX_FEED_BYTES) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        } finally {
            unset($curl);
        }
        if ($status === 404) return null;
        if ($ok === false || $status !== 200 || stripos((string)$contentType, 'text/html') === 0) {
            throw new \RuntimeException('El canal oficial de actualizaciones no está disponible.');
        }
        return self::offer(trim($body), $from);
    }

    public static function offer(string $code, string $from): array
    {
        $claims = LicenseCode::open($code, self::SIGNATURE_PREFIX, self::MAX_FEED_BYTES);
        $version = $claims['version'] ?? null;
        $hash = $claims['sha256'] ?? null;
        $signature = $claims['signature'] ?? null;
        if (($claims['v'] ?? null) !== 1 || ($claims['edition'] ?? null) !== 'commercial'
            || ($claims['from'] ?? null) !== $from || !self::validVersion($from)
            || !is_string($version) || !self::validVersion($version) || !version_compare($version, $from, '>')
            || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !is_string($signature)) {
            throw new \InvalidArgumentException('El aviso de actualización no corresponde a esta versión comercial.');
        }
        $package = LicenseCode::open($signature, 'NFXU1', 2048);
        if (($package['v'] ?? null) !== 1 || ($package['type'] ?? null) !== 'update' || ($package['sha256'] ?? null) !== $hash) {
            throw new \InvalidArgumentException('La firma del ZIP no corresponde al aviso de actualización.');
        }
        return [
            'version' => $version,
            'url' => self::BASE.$from.'-to-'.$version.'-'.$hash.'.zip',
            'signature' => $signature,
        ];
    }

    private static function validVersion(string $version): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.(?:0|[1-9][0-9]{0,5})){2}$/D', $version) === 1;
    }
}
