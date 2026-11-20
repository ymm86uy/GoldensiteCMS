<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,CacheStore,Csrf,DashboardData,Database,OwnerAccess,ProductEdition,RequestGuard,Settings,ThemeService};
final class DashboardController
{
    public function index(): void
    {
        Auth::requireAdmin(); $db=Database::getInstance();
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && ($_GET['api']??'')==='geo') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(\App\Geo\ActivityMap::activeAccounts(),JSON_THROW_ON_ERROR);
            return;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                if (($_POST['action']??'')==='check_integrity') { \App\Core\ReleaseIntegrity::checkRequest(); $msg='Comprobación de integridad finalizada. Consulta el resultado y su fecha.'; }
                elseif (($_POST['action']??'')==='clear_cache') { $count=CacheStore::clear(); $msg="Caché limpiada: $count archivos."; }
                elseif (($_POST['action']??'')==='revoke_session') {
                    $hash=$_POST['session_hash']??'';
                    if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new \InvalidArgumentException('Sesión inválida.');
                    $target=$db->fetch('SELECT user_id FROM active_sessions WHERE session_id = ?',[$hash]);
                    if (!$target) throw new \InvalidArgumentException('La sesión ya no está disponible.');
                    $owner=ProductEdition::current() === 'owner' ? OwnerAccess::configuration() : null;
                    if ($owner && (int)$target['user_id']===(int)$owner['user_id'] && !OwnerAccess::eligible()) throw new \InvalidArgumentException('Solo el dueño puede revocar sus sesiones.');
                    $conn=$db->getConnection(); $conn->beginTransaction();
                    try { $conn->prepare('INSERT INTO goldensite_revoked_sessions (session_hash, revoked_by, expires_at) VALUES (?, ?, DATE_ADD(NOW(),INTERVAL 3 HOUR)) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at)')->execute([$hash,Auth::id()]); $conn->prepare('DELETE FROM active_sessions WHERE session_id = ?')->execute([$hash]); $conn->commit(); }
                    catch (\Throwable $e) { $conn->rollBack(); throw $e; }
                    if (hash_equals(hash('sha256',session_id()),$hash)) { Auth::logout(); header('Location: /login'); return; }
                    $msg='Sesión revocada. Su siguiente solicitud exigirá iniciar sesión.';
                } else throw new \InvalidArgumentException('Acción desconocida.');
                $_SESSION['dashboard_msg']=['type'=>'success','msg'=>$msg]; header('Location: /admin/dashboard'); return;
            } catch (\InvalidArgumentException $e) { http_response_code(422); $status=['type'=>'danger','msg'=>$e->getMessage()]; }
            catch (\RuntimeException $e) { error_log('Dashboard operation failed: '.$e->getMessage()); http_response_code(503); $status=['type'=>'danger','msg'=>'No se pudo completar la operación. Revisa la disponibilidad de la referencia oficial y el registro del servidor.']; }
        }
        $summary=DashboardData::summary();
        if (($_GET['api']??'')==='summary') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($summary,JSON_THROW_ON_ERROR); return; }
        $stats=$summary['metrics']; $visits=DashboardData::visits(); $cache=CacheStore::summary();
        $activeSessions=$db->fetchAll('SELECT session_id, user_id, username, role, ip_address, last_url, last_activity FROM active_sessions WHERE last_activity >= NOW()-INTERVAL 15 MINUTE ORDER BY last_activity DESC LIMIT 12');
        $recentContent=$db->fetchAll("SELECT title, slug, status, 'Página' AS kind, created_at FROM goldensite_pages UNION ALL SELECT title, slug, status, 'Artículo' AS kind, created_at FROM goldensite_posts ORDER BY created_at DESC LIMIT 6");
        $recentEvents=$db->fetchAll('SELECT action, username, created_at FROM goldensite_activity_logs ORDER BY id DESC LIMIT 5');
        $search=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):''; $page=max(1,min(10000,(int)($_GET['page']??1))); $offset=($page-1)*12;
        $where=$search!==''?' WHERE username LIKE ? OR email LIKE ?':''; $params=$search!==''?['%'.$search.'%','%'.$search.'%']:[];
        $userTotal=(int)$db->fetch('SELECT COUNT(*) AS total FROM users'.$where,$params)['total'];
        $registeredUsers=$db->fetchAll('SELECT id, username, email, role, status, created_at, EXISTS(SELECT 1 FROM goldensite_scim_users s WHERE s.user_id=users.id AND s.deleted=0) AS scim_managed FROM users'.$where.' ORDER BY id DESC LIMIT 12 OFFSET '.$offset,$params);
        $ownerId=ProductEdition::current()==='owner' ? (int)(OwnerAccess::configuration()['user_id']??0) : 0;
        $theme=ThemeService::active(); $worker=(int)Settings::get('maintenance_worker_seen','0');
        $status??=$_SESSION['dashboard_msg']??null; unset($_SESSION['dashboard_msg']);
        $title='Centro de operaciones · Goldensite'; $csrfToken=Csrf::generate(); ob_start(); require dirname(__DIR__,3).'/views/admin/dashboard.php'; $content=ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }
}
