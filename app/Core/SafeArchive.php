<?php
namespace App\Core;

final class SafeArchive
{
    public static function pathAllowed(string $name): bool
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, ':') || str_contains($name, "\0") || str_starts_with($name, '/')) return false;
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || $part[0] === '.') return false;
        }
        if ($name === 'database/schema.sql' || $name === 'composer.json' || $name === 'composer.lock') return true;
        if (!preg_match('~^(app|views|routes|themes|modules|plugins|vendor|public|tools)/~', $name)) return false;
        if (preg_match('~^public/(uploads|cache|downloads|updates|assets/site-icons)(/|$)~i', $name)) return false;
        foreach (explode('/',rtrim($name,'/')) as $part) {
            if (preg_match('/[<>|"!?*\x00-\x1f]|[. ]$|^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i',$part)) return false;
        }
        return true;
    }

    public static function read(string $archive, string $root, bool $versioned = false): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) throw new \RuntimeException('ZIP inválido.');
        $root = realpath($root);
        if (!$root) throw new \RuntimeException('Destino inválido.');
        $files = [];
        $seen = [];
        $bytes = 0;
        try {
            if ($zip->numFiles > 10000) throw new \RuntimeException('Demasiados archivos.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if ($versioned && $name === LegacyFormat::PREFIX.'-update.json') $name = ReleaseUpdate::MANIFEST;
                if (!self::pathAllowed($name) && !($versioned && $name === ReleaseUpdate::MANIFEST)) throw new \RuntimeException('Ruta de actualización no permitida.');
                $key = strtolower(rtrim($name,'/'));
                if (isset($seen[$key])) throw new \RuntimeException('Ruta duplicada en la actualización.');
                $seen[$key] = true;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000) throw new \RuntimeException('Enlaces simbólicos no permitidos.');
                $bytes += $stat['size'];
                if ($bytes > 104857600 || $stat['size'] > 10485760) throw new \RuntimeException('Actualización demasiado grande.');
                $path = $root;
                $segments = explode('/',rtrim($name,'/'));
                foreach ($segments as $index=>$segment) {
                    $path .= '/' . $segment;
                    if (is_link($path)) throw new \RuntimeException('Destino enlazado no permitido.');
                    if (file_exists($path)) {
                        if (($index<count($segments)-1 || str_ends_with($name,'/')) && !is_dir($path)) throw new \RuntimeException('Un directorio del ZIP coincide con un archivo.');
                        $resolved = str_replace('\\', '/', realpath($path));
                        if (!str_starts_with($resolved . '/', str_replace('\\', '/', $root) . '/')) throw new \RuntimeException('Destino fuera del proyecto.');
                    }
                }
                if (str_ends_with($name, '/')) continue;
                if (isset($files[$name])) throw new \RuntimeException('Ruta duplicada en la actualización.');
                $content = $zip->getFromIndex($i);
                if ($content === false || strlen($content) !== $stat['size']) throw new \RuntimeException('Archivo ZIP incompleto.');
                if (str_ends_with(strtolower($name), '.php')) token_get_all($content, TOKEN_PARSE);
                $files[$name] = $content;
            }
        } finally { $zip->close(); }
        $filePaths = array_fill_keys(array_map('strtolower',array_keys($files)),true);
        foreach (array_keys($filePaths) as $name) {
            for ($parent=dirname($name); $parent!=='.'; $parent=dirname($parent)) {
                if (isset($filePaths[$parent])) throw new \RuntimeException('El ZIP usa un archivo como directorio.');
            }
        }
        return $files;
    }

    public static function apply(string $archive, string $root): void
    {
        $files = self::read($archive,$root);
        $root = realpath($root);
        $originals = [];
        try {
            foreach ($files as $name => $content) {
                $path = $root . '/' . $name;
                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) throw new \RuntimeException('No se pudo crear el destino.');
                $originals[$path] = is_file($path) ? file_get_contents($path) : null;
                if (file_put_contents($path, $content, LOCK_EX) !== strlen($content)) throw new \RuntimeException('No se pudo escribir la actualización.');
            }
        } catch (\Throwable $error) {
            foreach ($originals as $path => $content) {
                if ($content === null) @unlink($path);
                else file_put_contents($path, $content, LOCK_EX);
            }
            throw $error;
        }
    }
}
