<?php
namespace App\Controllers;

use App\Core\{Auth,PasswordRecovery,RateLimiter};

final class PasswordRecoveryController
{
    public function forgot(): void { $this->show(false); }
    public function reset(): void { $this->show(true); }

    private function show(bool $reset): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        $route=$reset?'/login/reset':'/login/forgot';
        if ($reset && array_key_exists('token',$_GET)) {
            unset($_SESSION['password_reset_token']);
            if (PasswordRecovery::validToken($_GET['token'])) $_SESSION['password_reset_token']=$_GET['token'];
            header('Location: '.$route,true,303); return;
        }
        $error=null;
        $notice=null;
        $token=$_SESSION['password_reset_token']??'';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            try {
                if (!RateLimiter::consume('password-recovery:'.($_SERVER['REMOTE_ADDR']??'unknown'),10,900)) throw new \InvalidArgumentException('Espera antes de volver a intentarlo.');
                if ($reset) {
                    if (!PasswordRecovery::reset($token,$_POST['new_password']??null,$_POST['confirm_password']??null)) throw new \InvalidArgumentException('El enlace venció o ya se utilizó. Solicita uno nuevo.');
                    Auth::logout();
                    \App\Core\Session::start();
                    $_SESSION['login_notice']='Contraseña actualizada. Inicia sesión con la nueva contraseña.';
                    header('Location: /login',true,303); return;
                }
                PasswordRecovery::request($_POST['email']??null);
                $notice='Si existe una cuenta activa con ese correo, recibirás un enlace para restablecer tu contraseña.';
            } catch (\InvalidArgumentException $exception) { $error=$exception->getMessage(); }
            catch (\Throwable $exception) { error_log('Password recovery operation failed.'); $error='El servicio no está disponible en este momento.'; }
        }
        require dirname(__DIR__,2).'/views/auth/password_recovery.php';
    }
}
