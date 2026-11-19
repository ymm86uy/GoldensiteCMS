<?php
namespace App\Core;
final class IdentityAccess
{
    private static array $cache=[];
    public static function requiresMfa(): bool
    {
        if (!Auth::check()) return false;
        return self::superuser()
            ? Settings::get('admin_require_mfa','0')==='1'
            : !empty(self::role()['require_mfa']);
    }
    public static function permissions(): array { return ['dashboard.read','users.read','users.manage','pages.read','pages.write','pages.review','pages.publish','posts.read','posts.write','posts.review','posts.publish','menus.read','menus.write','messages.read','messages.write','activity.read','activity.export','appearance.read','appearance.write','builder.read','builder.write','modules.read','modules.write','settings.read','settings.write','security.read','security.write','forms.read','forms.write','deploy.read','deploy.write','identity.manage','forum.moderate','forum.participate','social.publish','social.moderate']; }
    public static function superuser(?array $user=null): bool { $user??=Auth::user(); return $user && (($user['role']??'')==='admin'||(int)($user['role_id']??0)===1); }
    public static function role(?int $id=null): ?array { $id??=(int)Auth::id(); if (!array_key_exists($id,self::$cache)) self::$cache[$id]=Database::getInstance()->fetch('SELECT r.* FROM goldensite_access_roles r JOIN goldensite_access_members m ON m.role_id=r.id WHERE m.user_id=?',[$id])?:null; return self::$cache[$id]; }
    public static function reset(): void { self::$cache=[]; }
    public static function allows(string $permission): bool {
        if (self::superuser()) return true; if (!Auth::check()) return false;
        $role=self::role(); if (!$role) return false;
        $allowed=json_decode($role['permissions_json'],true); if (!is_array($allowed)||!in_array($permission,$allowed,true)) return false;
        if ($role['require_mfa'] && empty($_SESSION['mfa_verified'])) return false;
        $networks=json_decode($role['networks_json']??'[]',true); if ($networks) { foreach ($networks as $network) if (\App\Security\IpRange::contains($network,$_SERVER['REMOTE_ADDR']??'')) return true; return false; }
        return true;
    }
    public static function canEnter(): bool { if (self::superuser()) return true; foreach (self::permissions() as $p) if (!in_array($p,['forum.participate','social.publish'],true) && self::allows($p)) return true; return false; }
    public static function permissionFor(string $path,string $method='GET'): string {
        $path=AdminPath::normalize($path); $part=explode('/',trim($path,'/'))[1]??'dashboard'; $write=!in_array(strtoupper($method),['GET','HEAD','OPTIONS'],true);
        if ($part==='owner') return 'owner';
        if ($part==='social-network') return 'social.moderate';
        if ($part==='forum') return 'forum.moderate';
        if ($part==='identity-access') return 'identity.manage';
        if ($part==='activity-log') return isset($_GET['export'])?'activity.export':'activity.read';
        if ($part==='support-desk') return $write?'messages.write':'messages.read';
        $scope=['dashboard'=>'dashboard','users'=>'users','pages'=>'pages','posts'=>'posts','menus'=>'menus','messages'=>'messages','themes'=>'appearance','typography'=>'appearance','accessibility'=>'appearance','ai-builder'=>'builder','plugins'=>'modules','settings'=>'settings','waf'=>'security','form-builder'=>'forms','deploy-manager'=>'deploy'][$part]??null;
        if (!$scope) return 'legacy.superuser';
        if ($write && in_array($scope,['pages','posts'],true) && count(explode('/',trim($path,'/')))===2) {
            if (($_POST['action']??'')==='approve_review') return $scope.'.review';
            if (in_array($_POST['action']??'',['schedule_publish','cancel_schedule'],true)) return $scope.'.publish';
        }
        if ($scope==='dashboard' && $write) return ($_POST['action']??'')==='revoke_session'?'users.manage':'settings.write';
        return $scope.'.'.($write?($scope==='users'?'manage':'write'):'read');
    }
    public static function enforce(string $path,string $method): void { if (!self::allows(self::permissionFor($path,$method))) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit('Tu cuenta no tiene permiso para esta operación.'); } }
}
