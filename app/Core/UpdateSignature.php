<?php
namespace App\Core;
final class UpdateSignature
{
    public static function verify(string $archive, string $code): void
    {
        $claims = LicenseCode::open(trim($code), 'NFXU1', 2048);
        if (($claims['v'] ?? null) !== 1 || ($claims['type'] ?? '') !== 'update' || !is_string($claims['sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $claims['sha256']) || !is_file($archive) || !hash_equals($claims['sha256'], hash_file('sha256', $archive))) throw new \InvalidArgumentException('El paquete no coincide con su firma oficial.');
    }
}
