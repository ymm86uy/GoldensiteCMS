<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,EnterpriseOidc,IdentityAccess,RequestGuard,SecretStore,Settings};
final class IdentityController
{
    public function index(): void {
        Auth::requireAdmin(); $db=Database::getInstance(); $status=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            ConsoleView::password(); $action=ConsoleView::field($_POST,'action',20);
            if ($action==='mfa-policy') {
                if (!IdentityAccess::superuser()) { http_response_code(403); return; }
                $enabled=($_POST['admin_require_mfa']??'')==='1';
                $account=$db->fetch('SELECT totp_secret FROM users WHERE id=?',[Auth::id()]);
                if (empty($account['totp_secret']) || empty($_SESSION['mfa_verified'])) throw new \InvalidArgumentException('Configura y verifica tu segundo factor antes de cambiar esta política.');
                Settings::set('admin_require_mfa',$enabled?'1':'0');
            } elseif ($action==='role') {
                $name=ConsoleView::field($_POST,'name',80); $permissions=$_POST['permissions']??[]; $networks=preg_split('/\s+/',ConsoleView::field($_POST,'networks',2000),-1,PREG_SPLIT_NO_EMPTY);
                if ($name===''||!is_array($permissions)||!array_is_list($permissions)||!$permissions||array_diff($permissions,IdentityAccess::permissions())) throw new \InvalidArgumentException('Selecciona un nombre y permisos válidos.');
                foreach ($permissions as $permission) if (!IdentityAccess::allows($permission)) throw new \InvalidArgumentException('No puedes delegar permisos que no posees.');
                foreach ($networks as $network) if (!\App\Security\IpRange::valid($network)) throw new \InvalidArgumentException('Red CIDR inválida.');
                $id=(int)($_POST['role_id']??0); $values=[$name,json_encode(array_values(array_unique($permissions)),JSON_THROW_ON_ERROR),json_encode($networks,JSON_THROW_ON_ERROR),($_POST['require_mfa']??'')==='1'?1:0];
                if ($id) { if (!$db->fetch('SELECT id FROM goldensite_access_roles WHERE id=?',[$id])) throw new \InvalidArgumentException('Rol inexistente.'); $db->execute('UPDATE goldensite_access_roles SET name=?,permissions_json=?,networks_json=?,require_mfa=? WHERE id=?',[...$values,$id]); } else $db->execute('INSERT INTO goldensite_access_roles (name,permissions_json,networks_json,require_mfa) VALUES (?,?,?,?)',$values);
            } elseif ($action==='assign') {
                $user=(int)($_POST['user_id']??0); $role=(int)($_POST['role_id']??0); $target=$db->fetch('SELECT id,role,role_id FROM users WHERE id=?',[$user]); if (!$target||IdentityAccess::superuser($target)||$user===(int)Auth::id()) throw new \InvalidArgumentException('Asigna roles a una cuenta de usuario distinta, sin privilegios de administrador completo.');
                if (!$role) $db->execute('DELETE FROM goldensite_access_members WHERE user_id=?',[$user]); else { $r=$db->fetch('SELECT permissions_json FROM goldensite_access_roles WHERE id=?',[$role]); if (!$r) throw new \InvalidArgumentException('Rol inexistente.'); foreach (json_decode($r['permissions_json'],true) as $p) if (!IdentityAccess::allows($p)) throw new \InvalidArgumentException('No puedes delegar permisos superiores.'); $db->execute('INSERT INTO goldensite_access_members (user_id,role_id) VALUES (?,?) ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)',[$user,$role]); }
            } elseif ($action==='oidc') {
                $issuer=ConsoleView::field($_POST,'oidc_issuer',255); $client=ConsoleView::field($_POST,'oidc_client_id',255); $secret=ConsoleView::field($_POST,'oidc_secret',2000); $enabled=($_POST['oidc_enabled']??'')==='1'; if ($issuer!=='') EnterpriseOidc::url($issuer); if ($enabled&&($issuer===''||$client==='')) throw new \InvalidArgumentException('Configura emisor y Client ID antes de habilitar.');
                $oldIssuer=Settings::get('oidc_issuer'); $oldClient=Settings::get('oidc_client_id'); $values=['oidc_issuer'=>rtrim($issuer,'/'),'oidc_client_id'=>$client,'oidc_enabled'=>$enabled?'1':'0']; if ($secret!=='') $values['oidc_secret']=SecretStore::seal($secret); elseif ($issuer!==$oldIssuer||$client!==$oldClient) $values['oidc_secret']=''; Settings::writeMany($values);
            } elseif ($action==='link') {
                $id=(int)($_POST['user_id']??0); $sub=ConsoleView::field($_POST,'subject',255); $issuer=Settings::get('oidc_issuer'); $target=$db->fetch('SELECT id,role,role_id FROM users WHERE id=?',[$id]); if (!$target||!$issuer||$sub===''||IdentityAccess::superuser($target)) throw new \InvalidArgumentException('Vincula una cuenta no administrativa mediante el subject exacto del proveedor configurado.');
                $db->execute('INSERT INTO goldensite_identity_links (user_id,issuer,subject_hash) VALUES (?,?,?)',[$id,$issuer,hash('sha256',$sub)]);
            } elseif ($action==='unlink') { $db->execute('DELETE FROM goldensite_identity_links WHERE id=?',[(int)($_POST['link_id']??0)]); }
            else throw new \InvalidArgumentException('Acción desconocida.');
            IdentityAccess::reset(); ConsoleView::audit('identity_'.$action); header('Location: /admin/identity-access'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } catch (\PDOException $e) { http_response_code(422); $status='El nombre o identidad ya está registrado.'; } }
        $roles=$db->fetchAll('SELECT * FROM goldensite_access_roles ORDER BY name'); $users=$db->fetchAll('SELECT u.id,u.username,u.role,u.role_id,m.role_id access_role FROM users u LEFT JOIN goldensite_access_members m ON m.user_id=u.id ORDER BY u.id DESC LIMIT 500');
        $links=$db->fetchAll('SELECT l.id,u.username,l.issuer FROM goldensite_identity_links l JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 100'); $selected=$db->fetch('SELECT * FROM goldensite_access_roles WHERE id=?',[(int)($_GET['role']??0)]);
        ConsoleView::render('identity',['roles'=>$roles,'users'=>$users,'links'=>$links,'selected'=>$selected,'status'=>$status],'Goldensite Identity & Access');
    }
}
