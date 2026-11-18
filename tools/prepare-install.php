<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
if (\App\Core\Installer::installed()) { fwrite(STDERR, "Goldensite ya está configurado. No se ha cambiado la instalación.\n"); exit(1); }
$root = dirname(__DIR__);
foreach (['storage', 'public/uploads', 'public/cache'] as $folder) {
    if (!is_dir($root . '/' . $folder) && !mkdir($root . '/' . $folder, 0755, true)) throw new RuntimeException('No se pudo preparar ' . $folder);
}
$path = $root . '/storage/install-token';
if (is_file($path)) $token = trim((string) file_get_contents($path));
else {
    $token = bin2hex(random_bytes(32));
    $file = fopen($path, 'x');
    if (!$file) throw new RuntimeException('No se pudo crear la clave privada de instalación.');
    @chmod($path, 0600);
    fwrite($file, $token);
    fclose($file);
}
echo "Instalador preparado. Abre /install.php y utiliza esta clave de un solo uso:\n$token\n";
echo "No compartas esta clave. El asistente te pedirá el usuario y la contraseña administradores que tú elijas.\n";
