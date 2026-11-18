<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Allow: POST'); respond(405, ['ok' => false, 'error' => 'Método no permitido.']); }
$user = Auth::user();
if (!$user || (($user['role'] ?? '') !== 'admin' && ($user['role_id'] ?? 0) !== 1)) respond(403, ['ok' => false, 'error' => 'Acceso denegado.']);
if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) respond(403, ['ok' => false, 'error' => 'Token de seguridad inválido.']);
$raw = file_get_contents('php://input', false, null, 0, 2097153);
if (strlen($raw) > 2097152) respond(413, ['ok' => false, 'error' => 'Contenido demasiado grande.']);
$input = json_decode($raw, true);
if (!is_array($input) || !is_string($input['title'] ?? null) || !is_array($input['blocks'] ?? null) || !is_array($input['global'] ?? null)) respond(422, ['ok' => false, 'error' => 'Datos inválidos.']);
$id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$businessId = filter_var($input['business_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$title = trim($input['title']);
$status = $input['status'] ?? 'draft';
if ($id === false || $businessId === false || $title === '' || mb_strlen($title) > 255 || !in_array($status, ['draft', 'published'], true)) respond(422, ['ok' => false, 'error' => 'Datos inválidos.']);
try {
    $db = Database::getInstance();
    $params = ['title' => $title, 'blocks' => json_encode($input['blocks'], JSON_THROW_ON_ERROR), 'global' => json_encode($input['global'], JSON_THROW_ON_ERROR), 'status' => $status, 'business' => $businessId];
    if ($id > 0) {
        if (!$db->fetch('SELECT id FROM pages WHERE id = :id AND business_id = :business', ['id' => $id, 'business' => $businessId])) respond(404, ['ok' => false, 'error' => 'Página no encontrada.']);
        $params['id'] = $id;
        $db->execute('UPDATE pages SET title = :title, blocks_json = :blocks, global_json = :global, status = :status WHERE id = :id AND business_id = :business', $params);
    } else {
        $db->execute('INSERT INTO pages (business_id, title, blocks_json, global_json, status) VALUES (:business, :title, :blocks, :global, :status)', $params);
        $id = (int) $db->lastInsertId();
    }
    respond(200, ['ok' => true, 'id' => $id]);
} catch (Throwable $error) {
    error_log((string) $error);
    respond(500, ['ok' => false, 'error' => 'No se pudo guardar la página.']);
}
