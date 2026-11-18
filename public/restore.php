<?php
require_once dirname(__DIR__).'/app/bootstrap.php';

use App\Core\{AdminPath,Auth,IdentityAccess};

Auth::requireAdmin();
if (!IdentityAccess::superuser()) {
    http_response_code(403);
    exit('Restauración reservada a administradores completos.');
}
IdentityAccess::enforce('/admin/settings', $_SERVER['REQUEST_METHOD']);
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Usa la restauración protegida en Configuración.');
}
header('Location: '.AdminPath::url('/admin/settings'));