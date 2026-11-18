<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Installer;
use App\Core\RequestGuard;

header('Cache-Control: no-store, private');
if (Installer::installed()) {
    http_response_code(403);
    exit('GoldenSiteCMS ya está instalado. Accede a /login para administrar el sitio.');
}
$installToken = Installer::token();
if ($installToken === '') {
    http_response_code(403);
    exit('Prepara el instalador desde la consola del servidor: php tools/prepare-install.php. Después vuelve a abrir /install.php.');
}
$requirements = Installer::requirements();
$requirementsMet = !in_array(false, $requirements, true);
$errors = [];
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    RequestGuard::post();
    if (!is_string($_POST['install_token'] ?? null) || !hash_equals($installToken, $_POST['install_token'])) {
        http_response_code(403);
        exit('Clave de instalación incorrecta. Utiliza la clave privada generada en la consola del servidor.');
    }
    try {
        Installer::install($_POST);
        $success = true;
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
    } catch (PDOException $error) {
        error_log('Goldensite installation database error: ' . $error->getMessage());
        $mysqlCode = (int) ($error->errorInfo[1] ?? 0);
        $hint = match ($mysqlCode) {
            1045 => 'MySQL rechazó el acceso. Comprueba el nombre completo del usuario, su contraseña y el host autorizado en Webuzo.',
            1049 => 'MySQL no encuentra la base indicada. Comprueba su nombre completo, incluido el prefijo de Webuzo.',
            1044, 1142, 1143 => 'El usuario MySQL carece de permisos sobre la base. Asígnalo a esa base y revisa sus permisos en Webuzo.',
            2002, 2003, 2005 => 'No se pudo conectar a MySQL. Comprueba el servidor, el puerto y que el servicio de base de datos esté activo.',
            default => 'No se pudo preparar el esquema de la base de datos. Consulta el registro de PHP para identificar la operación que falló.',
        };
        $errors[] = $mysqlCode > 0 ? $hint . ' Código MySQL ' . $mysqlCode . '.' : $hint;
    } catch (Throwable $error) {
        error_log('Goldensite installation error: ' . $error->getMessage());
        $errors[] = 'No se pudo finalizar la instalación. Comprueba los permisos de escritura y el registro del servidor; puedes repetir el formulario con los mismos datos.';
    }
}
$defaultUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
function installValue(string $field, string $default = ''): string {
    $value = $_POST[$field] ?? $default;
    return htmlspecialchars(is_string($value) ? $value : $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - GoldenSiteCMS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #090d16; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 1rem; }
        .installer-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; width: 100%; max-width: 650px; padding: 2.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h1 { font-size: 1.8rem; color: #38bdf8; margin-bottom: 0.5rem; text-align: center; }
        p.subtitle { color: #94a3b8; text-align: center; font-size: 0.9rem; margin-bottom: 2rem; }
        .section-title { font-size: 1.1rem; color: #f8fafc; margin: 1.5rem 0 1rem 0; border-bottom: 1px solid #1e293b; padding-bottom: 0.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.3rem; }
        input { width: 100%; padding: 0.75rem; background: #1e293b; border: 1px solid #334155; border-radius: 6px; color: #fff; font-size: 0.95rem; outline: none; }
        input:focus { border-color: #38bdf8; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn { display: block; width: 100%; padding: 0.85rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 2rem; text-align: center; text-decoration: none; }
        .btn:hover { background: #0369a1; }
        .alert { padding: 1rem; border-radius: 6px; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #6ee7b7; }
        .check-list { list-style: none; margin-bottom: 1.5rem; }
        .check-list li { font-size: 0.85rem; padding: 0.4rem 0; color: #cbd5e1; }
        .pass { color: #10b981; font-weight: bold; }
        .fail { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>

<div class="installer-card">
    <h1>GoldenSiteCMS</h1>
    <p class="subtitle">Asistente de Instalación Autónoma</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>¡Instalación completada con éxito!</strong><br>
            Tu cuenta administradora ha sido creada con el usuario y la contraseña que acabas de elegir. Guarda esas credenciales para acceder al panel.
        </div>
        <a href="/login" class="btn">Ir al Panel de Administración →</a>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <p>• <?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section-title">1. Verificación del Servidor</div>
        <ul class="check-list">
            <?php foreach ($requirements as $label => $passed): ?>
                <li><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: <span class="<?= $passed ? 'pass' : 'fail' ?>"><?= $passed ? 'Correcto' : 'Requiere atención' ?></span></li>
            <?php endforeach; ?>
        </ul>

        <?php if ($requirementsMet): ?>
            <form action="/install.php" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>"><label>Clave de instalación</label><input type="password" name="install_token" required autocomplete="off">
                <div class="section-title">2. Conexión a la Base de Datos</div>
                <div class="grid-2">
                    <div class="form-group">
                        <label for="db_host">Servidor MySQL / Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= installValue('db_host', '127.0.0.1') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_port">Puerto</label>
                        <input type="text" id="db_port" name="db_port" value="<?= installValue('db_port', '3306') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="db_name">Nombre de la Base de Datos</label>
                    <input type="text" id="db_name" name="db_name" value="<?= installValue('db_name', 'goldensite_cms') ?>" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label for="db_user">Usuario MySQL</label>
                        <input type="text" id="db_user" name="db_user" value="<?= installValue('db_user') ?>" placeholder="Usuario de tu base de datos" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Contraseña MySQL</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                </div>

                <div class="section-title">3. Sitio Web</div>
                <div class="form-group"><label for="site_title">Nombre del sitio</label><input id="site_title" name="site_title" value="<?= installValue('site_title', 'GoldenSiteCMS') ?>" maxlength="150" required></div>
                <div class="form-group"><label for="site_url">Dirección principal del sitio</label><input type="url" id="site_url" name="site_url" value="<?= installValue('site_url', $defaultUrl) ?>" required><small>Ejemplo: https://tudominio.com</small></div>
                <div class="section-title">4. Cuenta Administradora Principal</div>
                <p>Elige tus credenciales. Esta será la cuenta con la que accederás a /login.</p>
                <div class="form-group">
                    <label for="admin_user">Usuario Administrador</label>
                    <input type="text" id="admin_user" name="admin_user" value="<?= installValue('admin_user', 'admin') ?>" minlength="3" maxlength="50" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="admin_email">Correo Electrónico</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= installValue('admin_email') ?>" placeholder="tu@correo.com" maxlength="150" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass">Contraseña</label>
                    <input type="password" id="admin_pass" name="admin_pass" minlength="12" maxlength="72" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn">Instalar GoldenSiteCMS Ahora</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error">Por favor, corrige los requisitos marcados en rojo antes de continuar.</div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
