<?php
namespace App\Core;

final class KeyStore
{
    public static function vault(): string
    {
        $configured = Environment::get('APP_KEY');
        if (strlen($configured) >= 32) return $configured;
        $directory = dirname(__DIR__, 2) . '/storage/keys';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Almacén de claves no disponible.');
        $path = $directory . '/vault.key';
        $file = fopen($path, 'c+');
        if (!$file || !flock($file, LOCK_EX)) throw new \RuntimeException('Clave de descargas no disponible.');
        try {
            $key = trim(stream_get_contents($file));
            if ($key === '') {
                $key = bin2hex(random_bytes(32));
                rewind($file);
                if (fwrite($file, $key) !== strlen($key)) throw new \RuntimeException('No se pudo guardar la clave de descargas.');
                fflush($file);
                @chmod($path, 0600);
            }
            if (strlen($key) < 32) throw new \RuntimeException('Clave de descargas inválida.');
            return $key;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
