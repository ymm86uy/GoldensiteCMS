<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$identifier = $argv[1] ?? '';
$password = getenv('GOLDENSITE_NEW_PASSWORD');
if ($identifier === '' || !is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Uso: define GOLDENSITE_NEW_PASSWORD (12 a 72 bytes) en el entorno y ejecuta php tools/reset-password.php usuario-o-correo.\n");
    exit(1);
}
$db = \App\Core\Database::getInstance();
$user = $db->fetch('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1', ['username' => $identifier, 'email' => $identifier]);
if (!$user) { fwrite(STDERR, "Cuenta no encontrada.\n"); exit(1); }
$db->execute("UPDATE users SET password = :password, status = 'active', updated_at = NOW() WHERE id = :id", ['password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $user['id']]);
$db->execute('DELETE FROM active_sessions WHERE user_id = :id', ['id' => $user['id']]);
echo "Contraseña actualizada.\n";
