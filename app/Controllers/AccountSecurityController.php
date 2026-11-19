<?php
namespace App\Controllers;
use App\Core\{Auth,ConsoleView,Csrf,Database,RateLimiter,RequestGuard,SecretStore,Settings,Totp};
final class AccountSecurityController
{
    public function index(): void {
        if (!Auth::check()) { header('Location: /login'); return; } header('Cache-Control: no-store, private'); header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        $db=Database::getInstance(); $user=$db->fetch('SELECT id,username,totp_secret FROM users WHERE id=?',[Auth::id()]); $status=null; $adminContext=\App\Core\IdentityAccess::superuser()||\App\Core\IdentityAccess::role()!==null; $returnUrl=$adminContext?\App\Core\AdminPath::url('/admin/dashboard'):'/mi-cuenta';
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            if (!RateLimiter::consume('mfa-setup:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.'); ConsoleView::password(); $action=ConsoleView::field($_POST,'action',20);
            if ($action==='start') { if ($user['totp_secret']) throw new \InvalidArgumentException('El segundo factor ya está activo.'); $bits=''; foreach (str_split(random_bytes(20)) as $byte) $bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT); $secret=''; for ($i=0;$i<strlen($bits);$i+=5) $secret.='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec(substr($bits,$i,5))]; $_SESSION['mfa_setup']=['secret'=>$secret,'until'=>time()+300]; }
            elseif ($action==='confirm') { $pending=$_SESSION['mfa_setup']??null; if (!$pending||$pending['until']<time()||$user['totp_secret']) throw new \InvalidArgumentException('La configuración venció o ya está activa.'); $counter=Totp::counter($pending['secret'],ConsoleView::field($_POST,'totp_code',6)); if ($counter===null) throw new \InvalidArgumentException('Código de autenticación inválido.'); $codes=[]; for ($i=0;$i<8;$i++) $codes[]=strtoupper(bin2hex(random_bytes(8))); $hashes=array_map(static fn($c)=>hash('sha256',$c),$codes); $db->execute('UPDATE users SET totp_secret=?,totp_last_counter=?,totp_recovery_json=? WHERE id=?',[SecretStore::seal($pending['secret']),$counter,json_encode($hashes,JSON_THROW_ON_ERROR),Auth::id()]); unset($_SESSION['mfa_setup']); $_SESSION['mfa_verified']=true; $_SESSION['mfa_recovery_codes']=$codes; ConsoleView::audit('mfa_enabled'); }
            elseif ($action==='disable') { if (\App\Core\IdentityAccess::requiresMfa()) throw new \InvalidArgumentException('El segundo factor es obligatorio para tu acceso administrativo.'); $counter=Totp::counter(SecretStore::open($user['totp_secret']??''),ConsoleView::field($_POST,'totp_code',6)); if ($counter===null) throw new \InvalidArgumentException('Introduce un código válido de tu autenticador.'); $db->execute('UPDATE users SET totp_secret=NULL,totp_last_counter=NULL,totp_recovery_json=NULL WHERE id=?',[Auth::id()]); unset($_SESSION['mfa_verified'],$_SESSION['mfa_setup']); ConsoleView::audit('mfa_disabled'); }
            else throw new \InvalidArgumentException('Acción desconocida.'); header('Location: /account/security'); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $pending=$_SESSION['mfa_setup']??null; if ($pending&&$pending['until']<time()) { unset($_SESSION['mfa_setup']); $pending=null; } $codes=$_SESSION['mfa_recovery_codes']??[]; unset($_SESSION['mfa_recovery_codes']); $e=ConsoleView::escape(...); $siteTitle='Seguridad de mi cuenta · Goldensite'; ob_start(); ?>
        <?php require dirname(__DIR__,2).'/views/account/security.php'; ?>
        <?php $content=ob_get_clean(); if ($adminContext) { $title=$siteTitle; require dirname(__DIR__,2).'/views/layouts/admin.php'; } else require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}

