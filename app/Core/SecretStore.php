<?php
namespace App\Core;
final class SecretStore
{
    public static function seal(string $value): string
    {
        $iv=random_bytes(12); $key=hash_hmac('sha256',LegacyFormat::PREFIX.'-settings:v1',KeyStore::vault(),true);
        $cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'smtp_pass');
        if ($cipher===false) throw new \RuntimeException('No se pudo cifrar la credencial.');
        return 'enc:v1:'.base64_encode($iv.$tag.$cipher);
    }
    public static function open(string $value): string
    {
        if (!str_starts_with($value,'enc:v1:')) return $value; // Migration compatibility; never echo this value into forms.
        $raw=base64_decode(substr($value,7),true);
        if ($raw===false || strlen($raw)<28) throw new \RuntimeException('Credencial cifrada inválida.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash_hmac('sha256',LegacyFormat::PREFIX.'-settings:v1',KeyStore::vault(),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'smtp_pass');
        if ($plain===false) throw new \RuntimeException('No se pudo descifrar la credencial con la clave del sitio.');
        return $plain;
    }
}
