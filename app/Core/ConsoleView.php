<?php
namespace App\Core;
final class ConsoleView
{
    public static function escape(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function render(string $view,array $data,string $title): void { extract($data,EXTR_SKIP); $csrfToken=Csrf::generate(); $e=self::escape(...); ob_start(); require dirname(__DIR__,2).'/views/admin/'.$view.'.php'; $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/admin.php'; }
    public static function field(array $data,string $key,int $max=255): string { return LicenseService::field($data,$key,$max); }
    public static function password(): void { $u=Database::getInstance()->fetch('SELECT password FROM users WHERE id=?',[Auth::id()]); $p=$_POST['current_password']??''; if (!is_string($p)||strlen($p)>72||!$u||!password_verify($p,$u['password'])) throw new \InvalidArgumentException('Confirma tu contraseña actual.'); }
    public static function audit(string $event,string $description=''): void { \App\Controllers\Admin\ActivityLogController::log($event,$description); }
}
