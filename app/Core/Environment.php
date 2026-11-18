<?php

namespace App\Core;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match)) continue;
            if (getenv($match[1]) !== false || isset($_ENV[$match[1]])) continue;
            $value = trim($match[2]);
            if (str_starts_with($value, '"')) {
                $decoded = json_decode($value, true);
                if (!is_string($decoded)) continue;
                $value = $decoded;
            } elseif (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }
            $_ENV[$match[1]] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false || isset($_ENV[$key])) return $value !== false ? $value : (string)$_ENV[$key];
        if (str_starts_with($key,'GOLDENSITE_')) {
            $legacy = strtoupper(LegacyFormat::PREFIX).'_'.substr($key,strlen('GOLDENSITE_'));
            $value = getenv($legacy);
            if ($value !== false || isset($_ENV[$legacy])) return $value !== false ? $value : (string)$_ENV[$legacy];
        }
        return $default;
    }
}
