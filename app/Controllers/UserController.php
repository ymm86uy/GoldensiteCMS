<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Csrf;

class UserController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $db = Database::getInstance();
        
        try {
            $conn = $db->getConnection();
            $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
            $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role_id INT DEFAULT 2");
            $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
        } catch (\Throwable $e) {}

        $users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");

        $title = "Gestión de Usuarios y Permisos - GoldenSiteCMS";

        ob_start();
        require_once __DIR__ . '/../../views/admin/users/index.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../views/layouts/admin.php';
    }

    public function create(): void
    {
        Auth::requireAdmin();

        $title = "Nuevo Usuario - GoldenSiteCMS";

        ob_start();
        require_once __DIR__ . '/../../views/admin/users/create.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../views/layouts/admin.php';
    }

    public function store(): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            header('Location: /admin/users');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';
        $roleId   = ($role === 'admin') ? 1 : 2;

        if (!empty($username) && !empty($email) && !empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO users (username, name, email, password, role, role_id, status, created_at) VALUES (:u, :u2, :e, :p, :r, :rid, 'active', NOW())",
                [
                    'u'   => $username,
                    'u2'  => $username,
                    'e'   => $email,
                    'p'   => $hashedPassword,
                    'r'   => $role,
                    'rid' => $roleId
                ]
            );
        }

        header('Location: /admin/users');
        exit;
    }

    public function updateRole(): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['id'] ?? 0);
        $role   = $_POST['role'] ?? 'user';
        $roleId = ($role === 'admin') ? 1 : 2;

        if ($userId > 0) {
            $db = Database::getInstance();
            $db->execute("UPDATE users SET role = :r, role_id = :rid WHERE id = :id", [
                'r'   => $role,
                'rid' => $roleId,
                'id'  => $userId
            ]);
        }

        header('Location: /admin/users');
        exit;
    }

    public function resetPassword(): void
    {
        Auth::requireAdmin();

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['dashboard_msg'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
            header('Location: /admin/dashboard');
            exit;
        }

        $userId      = (int)($_POST['user_id'] ?? 0);
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $currentPassword = $_POST['current_password'] ?? '';
        $currentAccount = Database::getInstance()->fetch('SELECT password FROM users WHERE id = ?', [Auth::id()]);
        if (!is_string($currentPassword) || strlen($currentPassword)>72 || !$currentAccount || !password_verify($currentPassword, $currentAccount['password'])) {
            http_response_code(422); exit('Confirma tu contraseña actual antes de cambiar credenciales.');
        }
        $ownerConfig = \App\Core\ProductEdition::current() === 'owner' ? \App\Core\OwnerAccess::configuration() : null;
        $targetAccount=Database::getInstance()->fetch('SELECT id,role,role_id FROM users WHERE id=?',[$userId]);
        if (!$targetAccount || (\App\Core\IdentityAccess::superuser($targetAccount) && !\App\Core\IdentityAccess::superuser())) { http_response_code(403); exit('No puedes modificar una cuenta con privilegios superiores.'); }
        if ($ownerConfig && $userId === (int)$ownerConfig['user_id'] && !\App\Core\OwnerAccess::eligible()) { http_response_code(403); exit('La cuenta Owner administra sus propias credenciales.'); }

        if ($userId > 0 && strlen($newPassword) >= 12 && strlen($newPassword) <= 72) {
            try {
                $db = Database::getInstance();
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->execute("UPDATE users SET password = :pass, updated_at = NOW() WHERE id = :id", [
                    'pass' => $hashedPassword,
                    'id'   => $userId
                ]);
                if ($userId === Auth::id()) {
                    Auth::logout();
                    \App\Core\Session::start();
                    $_SESSION['login_notice'] = 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.';
                    header('Location: /login');
                    exit;
                }

                $_SESSION['dashboard_msg'] = [
                    'type' => 'success',
                    'msg'  => "🔑 Contraseña del usuario #{$userId} actualizada con éxito."
                ];
            } catch (\Throwable $e) {
                $_SESSION['dashboard_msg'] = ['type' => 'danger', 'msg' => 'Error al actualizar la contraseña en la base de datos.'];
            }
        } else {
            $_SESSION['dashboard_msg'] = ['type' => 'danger', 'msg' => 'La contraseña debe tener entre 12 y 72 bytes.'];
        }

        header('Location: /admin/dashboard');
        exit;
    }

}
