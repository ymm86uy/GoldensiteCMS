<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;

class AuthController
{
    public function showLogin(): void
    {
        \App\Core\AuthReturn::capture($_GET['return_to']??null);

        if (Auth::check()) {
            $user = Auth::user();
            if (\App\Core\IdentityAccess::canEnter()) {
                header('Location: '.\App\Core\AuthReturn::destination());
            } else {
                header('Location: '.\App\Core\AuthReturn::destination());
            }
            exit;
        }

        $error = $_SESSION['login_error'] ?? null;
        $notice = $_SESSION['login_notice'] ?? null;
        unset($_SESSION['login_error']);
        unset($_SESSION['login_notice']);

        require_once __DIR__ . '/../../views/auth/login.php';
    }

    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();


        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['login_error'] = 'Token de seguridad CSRF inválido o expirado.';
            header('Location: /login');
            exit;
        }

        $identifier = $_POST['email'] ?? $_POST['username'] ?? $_POST['identifier'] ?? '';
        $emailOrUsername = is_string($identifier) ? trim($identifier) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (Auth::login($emailOrUsername, $password)) {
            if (!empty($_SESSION['2fa_pending_user_id'])) {
                header('Location: /login/2fa');
                exit;
            }
            $user = Auth::user();
            if (\App\Core\IdentityAccess::canEnter()) {
                header('Location: '.\App\Core\AuthReturn::destination());
            } else {
                header('Location: '.\App\Core\AuthReturn::destination());
            }
            exit;
        }

        $_SESSION['login_error'] = Auth::loginWasThrottled()
            ? 'Demasiados intentos de acceso. Espera 15 minutos antes de volver a intentarlo.'
            : 'Usuario o contraseña incorrectos, o cuenta inactiva. Comprueba tus credenciales actuales.';
        header('Location: /login');
        exit;
    }

    public function showRegister(): void
    {
        \App\Core\AuthReturn::capture($_GET['return_to']??null);
        header('Cache-Control: no-store, private');
        if (Auth::check()) {
            header('Location: '.\App\Core\AuthReturn::destination());
            return;
        }

        $pending = \App\Core\AccountRegistration::pending();
        $verify = $pending && ($_GET['step']??'')==='verify';
        $error = $_SESSION['register_error'] ?? null;
        $notice = $_SESSION['register_notice'] ?? null;
        unset($_SESSION['register_error'],$_SESSION['register_notice']);
        require __DIR__.'/../../views/auth/register.php';
    }

    public function register(): void
    {
        \App\Core\RequestGuard::post();
        if (Auth::check()) {
            header('Location: '.\App\Core\AuthReturn::destination(),true,303);
            return;
        }
        $action = $_POST['action']??'begin';
        $destination = $action==='begin'?'/register':'/register?step=verify';
        try {
            if ($action==='verify') {
                $account = \App\Core\AccountRegistration::activate($_POST['activation_code']??null);
                if (!$account) throw new \InvalidArgumentException('Código incorrecto, vencido o sin intentos disponibles. Puedes solicitar otro.');
                $_SESSION['login_notice'] = 'Cuenta activada. Ya puedes iniciar sesión.';
                $destination = '/login';
                try {
                    \App\Controllers\Admin\WebhooksController::dispatch('user_registered','Se activó una cuenta nueva en el sitio.');
                    \App\Core\AutomationEngine::trigger('user_registered',$account);
                } catch (\Throwable $error) {
                    error_log('Registration completed; notification dispatch failed.');
                }
            } elseif ($action==='begin' || $action==='resend') {
                $sent = $action==='begin'?\App\Core\AccountRegistration::begin($_POST):\App\Core\AccountRegistration::resend();
                $destination = '/register?step=verify';
                if ($sent) $_SESSION['register_notice'] = 'Si los datos permiten crear la cuenta, recibirás un código en tu correo.';
                else $_SESSION['register_error'] = 'No se pudo enviar el código. Puedes volver a solicitarlo en un minuto.';
            } else {
                http_response_code(400);
                return;
            }
        } catch (\InvalidArgumentException $error) {
            $_SESSION['register_error'] = $error->getMessage();
        } catch (\Throwable $error) {
            error_log('Registration operation failed.');
            $_SESSION['register_error'] = 'No se pudo completar el registro. Inténtalo más tarde.';
        }
        header('Location: '.$destination,true,303);
    }

    public function logout(): void
    {
        \App\Core\RequestGuard::post();
        Auth::logout();
        header('Location: /login');
        exit;
    }

    public function show2fa(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['2fa_pending_user_id'])) {
            header('Location: /login');
            exit;
        }

        $title = "Verificación 2FA - GoldenSiteCMS";
        ob_start();
        ?>
        <div style="max-width: 400px; margin: 4rem auto; background: #1e293b; border: 1px solid #334155; padding: 2rem; border-radius: 1rem; color: #fff; font-family: ui-sans-serif, system-ui;">
            <h2 style="text-align: center; font-size: 1.25rem; font-weight: 900; margin-bottom: 0.5rem; color: #eab308;">🔑 Verificación 2FA</h2>
            <p style="text-align: center; font-size: 0.8rem; color: #94a3b8; margin-bottom: 1.5rem;">Ingresa el código de tu autenticador o uno de tus códigos de recuperación.</p>
            
            <?php if (!empty($_SESSION['2fa_error'])): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; text-align: center; margin-bottom: 1rem;">
                    <?= htmlspecialchars($_SESSION['2fa_error']) ?>
                </div>
                <?php unset($_SESSION['2fa_error']); ?>
            <?php endif; ?>

            <form action="/login/2fa" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="text" name="totp_code" maxlength="19" autocomplete="one-time-code" aria-label="Código de autenticación o recuperación" required autofocus placeholder="000000" style="background: #0f172a; border: 1px solid #334155; color: #eab308; text-align: center; font-size: 1.5rem; font-weight: 900; letter-spacing: 0.5rem; padding: 0.5rem; border-radius: 8px;">
                <button type="submit" style="background: #eab308; color: #0f172a; font-weight: 900; padding: 0.75rem; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem;">Verificar Código</button>
            </form>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../views/layouts/frontend.php';
    }

    public function verify2fa(): void
    {
        \App\Core\RequestGuard::post();
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['2fa_pending_user_id'] ?? null;
        $code = \App\Core\ConsoleView::field($_POST,'totp_code',30);

        if (!$userId || empty($code)) {
            header('Location: /login');
            exit;
        }

        if (Auth::verifySecondFactor($code)) {
            header('Location: /admin/dashboard');
            exit;
        }

        $_SESSION['2fa_error'] = "Código incorrecto o expirado.";
        header('Location: /login/2fa');
        exit;
    }
}
