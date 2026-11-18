<?php
namespace App\Core;

use PDO;

final class Installer
{
    public static function installed(): bool
    {
        $root = dirname(__DIR__, 2);
        return is_file($root . '/storage/installed.lock') || is_file($root . '/config/installed.lock') ||
            (is_file($root . '/.env') && filesize($root . '/.env') > 0);
    }

    public static function token(): string
    {
        $token = Environment::get('GOLDENSITE_INSTALL_TOKEN');
        $path = dirname(__DIR__, 2) . '/storage/install-token';
        if ($token === '' && is_file($path)) $token = trim((string) file_get_contents($path));
        return strlen($token) >= 32 ? $token : '';
    }

    public static function requirements(): array
    {
        $root = dirname(__DIR__, 2);
        $supported = (PHP_VERSION_ID >= 80427 && PHP_VERSION_ID < 80500) || (PHP_VERSION_ID >= 80512 && PHP_VERSION_ID < 80600);
        return [
            'PHP 8.4.27+ o PHP 8.5.12+' => $supported,
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'Mbstring' => extension_loaded('mbstring'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'cURL' => extension_loaded('curl'),
            'Imágenes GD' => extension_loaded('gd'),
            'ZIP' => extension_loaded('zip'),
            'OpenSSL (licencias firmadas)' => extension_loaded('openssl'),
            'Escritura temporal de configuración (.env)' => is_writable($root),
            'Escritura en storage/' => is_dir($root . '/storage') && is_writable($root . '/storage'),
            'Escritura en public/uploads/' => is_dir($root . '/public/uploads') && is_writable($root . '/public/uploads'),
            'Escritura en public/cache/' => is_dir($root . '/public/cache') && is_writable($root . '/public/cache'),
        ];
    }

    public static function install(array $input): void
    {
        $root = dirname(__DIR__, 2);
        if (self::installed()) throw new \RuntimeException('La instalación ya está configurada.');
        $token = self::token();
        if ($token === '' || !is_string($input['install_token'] ?? null) || !hash_equals($token, $input['install_token'])) {
            throw new \InvalidArgumentException('La clave de instalación es incorrecta.');
        }
        if (in_array(false, self::requirements(), true)) throw new \RuntimeException('Corrige los requisitos del servidor antes de instalar.');
        foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'admin_user', 'admin_email', 'admin_pass', 'site_title', 'site_url'] as $field) {
            if (isset($input[$field]) && !is_string($input[$field])) throw new \InvalidArgumentException('El formulario contiene datos inválidos.');
        }
        $host = trim($input['db_host'] ?? '127.0.0.1');
        $port = trim($input['db_port'] ?? '3306');
        $name = trim($input['db_name'] ?? '');
        $dbUser = trim($input['db_user'] ?? '');
        $dbPassword = $input['db_pass'] ?? '';
        $username = trim($input['admin_user'] ?? '');
        $email = trim($input['admin_email'] ?? '');
        $password = $input['admin_pass'] ?? '';
        $siteTitle = trim($input['site_title'] ?? 'GoldenSiteCMS');
        $siteUrl = rtrim(trim($input['site_url'] ?? ''), '/');
        if (!preg_match('/^[A-Za-z0-9_.:\[\]-]{1,253}$/D', $host) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name) || $dbUser === '') {
            throw new \InvalidArgumentException('Comprueba el servidor, el puerto, el nombre de base de datos y su usuario.');
        }
        if (!preg_match('/^[\p{L}\p{N}_.-]{3,50}$/uD', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new \InvalidArgumentException('Introduce un usuario de 3 a 50 caracteres y un correo válido para el administrador.');
        }
        if (strlen($password) < 12 || strlen($password) > 72) throw new \InvalidArgumentException('La contraseña administradora debe tener entre 12 y 72 bytes.');
        $url = parse_url($siteUrl);
        if (!filter_var($siteUrl, FILTER_VALIDATE_URL) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])) {
            throw new \InvalidArgumentException('Introduce la dirección principal del sitio, por ejemplo https://tudominio.com, sin subcarpetas.');
        }
        if ($siteTitle === '' || mb_strlen($siteTitle) > 150) throw new \InvalidArgumentException('Introduce un nombre de sitio de hasta 150 caracteres.');

        $mutex = fopen($root . '/storage/install.mutex', 'c+');
        if (!$mutex || !flock($mutex, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Ya hay una instalación en curso. Espera a que termine.');
        $stagedEnv = null;
        try {
            if (self::installed()) throw new \RuntimeException('La instalación ya está configurada.');
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
            try {
                $db = new PDO($dsn . ";dbname=$name", $dbUser, $dbPassword, $options);
            } catch (\PDOException $error) {
                if (($error->errorInfo[1] ?? 0) !== 1049) throw $error;
                $db = new PDO($dsn, $dbUser, $dbPassword, $options);
                $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $db->exec("USE `$name`");
            }
            $owner = hash_hmac('sha256', "$host:$port/$name", $token);
            $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($tables && !in_array('goldensite_installation', $tables, true)) {
                throw new \InvalidArgumentException('La base contiene datos de otra instalación. Selecciona una base vacía; no se ha borrado ninguna tabla.');
            }
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_installation (id TINYINT PRIMARY KEY, installation_id CHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $state = $db->query('SELECT * FROM goldensite_installation WHERE id = 1')->fetch();
            if ($state && (!hash_equals($state['installation_id'], $owner) || $state['status'] === 'complete')) {
                throw new \InvalidArgumentException('La base pertenece a otra instalación o ya está instalada.');
            }
            if (!$state) $db->prepare("INSERT INTO goldensite_installation (id, installation_id, status) VALUES (1, ?, 'preparing')")->execute([$owner]);
            SchemaMigrator::migrate($db);
            $admin = $db->query('SELECT id, username, email, password FROM users ORDER BY id LIMIT 1')->fetch();
            if ($admin && ($admin['username'] !== $username || $admin['email'] !== $email || !password_verify($password, $admin['password']))) {
                throw new \InvalidArgumentException('La instalación pendiente ya tiene otro administrador. Repite los datos originales para finalizarla.');
            }
            $configuration = ['DB_HOST' => $host, 'DB_PORT' => $port, 'DB_NAME' => $name, 'DB_USER' => $dbUser, 'DB_PASS' => $dbPassword, 'APP_KEY' => bin2hex(random_bytes(32)), 'APP_URL' => $siteUrl, 'APP_ENV' => 'production'];
            $stagedEnv = $root . '/.env.install-' . bin2hex(random_bytes(8));
            $file = fopen($stagedEnv, 'x');
            if (!$file) throw new \RuntimeException('No se pudo preparar la configuración.');
            try {
                @chmod($stagedEnv, 0600);
                foreach ($configuration as $key => $value) {
                    $line = $key . '=' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                    if (fwrite($file, $line) !== strlen($line)) throw new \RuntimeException('No se pudo escribir la configuración.');
                }
                fflush($file);
            } finally { fclose($file); }
            $db->beginTransaction();
            try {
                if (!$admin) {
                    $stmt = $db->prepare("INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, 'admin', 1, 'active')");
                    $stmt->execute([$username, $username, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
                }
                $setting = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                foreach (['site_title' => $siteTitle, 'site_name' => $siteTitle, 'site_url' => $siteUrl, 'admin_email' => $email, 'site_description' => '', 'posts_per_page' => '10', 'maintenance_mode' => '0', 'active_theme' => 'dark-cyber'] as $key => $value) $setting->execute([$key, $value]);
                $db->exec("UPDATE goldensite_installation SET status = 'database_ready' WHERE id = 1");
                $db->commit();
            } catch (\Throwable $error) { $db->rollBack(); throw $error; }
            // The private authorization token also allows a failed file-writing step to be retried.
            if (self::installed() || !@rename($stagedEnv, $root . '/.env')) throw new \RuntimeException('No se pudo guardar .env. Comprueba los permisos del proyecto y repite la instalación.');
            $stagedEnv = null;
            if (file_put_contents($root . '/storage/installed.lock', date('c'), LOCK_EX) === false) {
                error_log('Goldensite: .env instalado; no se pudo guardar installed.lock. El archivo .env mantiene cerrado el instalador.');
            }
            $db->exec("UPDATE goldensite_installation SET status = 'complete' WHERE id = 1");
            @unlink($root . '/storage/install-token');
        } finally {
            if ($stagedEnv !== null && is_file($stagedEnv)) @unlink($stagedEnv);
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }
}
