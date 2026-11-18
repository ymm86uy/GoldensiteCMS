<?php

namespace App\Core;

final class RateLimiter
{
    public static function clear(string $key): void
    {
        $path = dirname(__DIR__, 2) . '/storage/rate-limits/' . hash('sha256', Environment::get('RATE_LIMIT_NAMESPACE') . '|' . $key) . '.json';
        if (!is_file($path)) return;
        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) throw new \RuntimeException('Control de intentos no disponible.');
        try { ftruncate($handle, 0); fflush($handle); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    // Shared across sessions. A caller cannot reset the budget by replacing cookies.
    public static function consume(string $key, int $limit = 10, int $window = 900): bool
    {
        $directory = dirname(__DIR__, 2) . '/storage/rate-limits';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo iniciar el control de intentos.');
        }
        $handle = fopen($directory . '/' . hash('sha256', Environment::get('RATE_LIMIT_NAMESPACE') . '|' . $key) . '.json', 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) throw new \RuntimeException('Control de intentos no disponible.');
        try {
            $state = json_decode(stream_get_contents($handle), true);
            if (!is_array($state) || ($state['expires'] ?? 0) <= time()) $state = ['expires' => time() + $window, 'count' => 0];
            $allowed = $state['count'] < $limit;
            if ($allowed) $state['count']++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state));
            fflush($handle);
            if (random_int(1, 100) === 1) {
                foreach (glob($directory . '/*.json') ?: [] as $candidate) {
                    if (filemtime($candidate) < time() - 86400) @unlink($candidate);
                }
            }
            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
