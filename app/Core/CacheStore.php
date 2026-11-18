<?php
namespace App\Core;
final class CacheStore
{
    public static function summary(): array
    {
        $files = [];
        foreach (['public/cache', 'storage/cache'] as $dir) foreach (glob(dirname(__DIR__,2).'/'.$dir.'/*') ?: [] as $path) if (is_file($path) && !is_link($path) && preg_match('/\.(?:html|json|cache)$/iD', $path)) $files[] = $path;
        return ['files' => $files, 'count' => count($files), 'bytes' => array_sum(array_map('filesize', $files))];
    }
    public static function clear(): int
    {
        $count = 0; foreach (self::summary()['files'] as $file) { if (!unlink($file)) throw new \RuntimeException('No se pudo borrar un archivo de caché.'); $count++; } return $count;
    }
}
