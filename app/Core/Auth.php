<?php

namespace App\Core;

class Auth
{
    private const SESSION_LIFETIME = 7200; // 2 horas
    private static bool $identityChecked = false;
    private static bool $loginThrottled = false;

    public static function loginWasThrottled(): bool
    {
        return self::$loginThrottled;
    }

    private static function initSession(): void
    {
        Session::start();
        self::validateSessionSecurity();
    }

    public static function logActivity(): void
    {
        self::initSession();
        if (!self::check()) return;

        try {
            $db = Database::getInstance()->getConnection();
            $db->exec("CREATE TABLE IF NOT EXISTS active_sessions (
                session_id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                role_id INT DEFAULT 2,
                last_url VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $sessId   = hash('sha256', session_id());
            $user     = self::user();
            $userId   = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);
            $username = $user['username'] ?? $_SESSION['username'] ?? 'Usuario';
            $email    = $user['email'] ?? $_SESSION['email'] ?? '';
            $role     = $user['role'] ?? $_SESSION['role'] ?? 'user';
            $roleId   = (int)($user['role_id'] ?? $_SESSION['role_id'] ?? 2);
            $url      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $agent    = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);

            $stmt = $db->prepare("INSERT INTO active_sessions 
                (session_id, user_id, username, email, role, role_id, last_url, ip_address, user_agent, last_activity)
                VALUES (:sid, :uid, :uname, :email, :role, :rid, :url, :ip, :agent, NOW())
                ON DUPLICATE KEY UPDATE 
                user_id = :uid2, username = :uname2, email = :email2, role = :role2, role_id = :rid2,
                last_url = :url2, ip_address = :ip2, user_agent = :agent2, last_activity = NOW()");

            $stmt->execute([
                'sid'    => $sessId,   'uid'    => $userId,   'uname'  => $username, 'email'  => $email,
                'role'   => $role,     'rid'    => $roleId,   'url'    => $url,      'ip'     => $ip,
                'agent'  => $agent,    'uid2'   => $userId,   'uname2' => $username, 'email2' => $email,
                'role2'  => $role,     'rid2'   => $roleId,   'url2'   => $url,      'ip2'    => $ip,
                'agent2' => $agent
            ]);
        } catch (\Throwable $e) {}
    }

    public static function login(string $usernameOrEmail, string $password): bool
    {
        self::initSession();

        self::$loginThrottled = false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (!RateLimiter::consume('login-ip:' . $ip, 30) ||
            !RateLimiter::consume('login-account:' . strtolower($usernameOrEmail), 10)) {
            self::$loginThrottled = true;
            return false;
        }

        $db = Database::getInstance();
        
        $user = $db->fetch("SELECT * FROM users WHERE (email = :id OR username = :id2) LIMIT 1", [
            'id'  => $usernameOrEmail,
            'id2' => $usernameOrEmail
        ]);

        if (!$user) {
            password_verify($password, '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            return false;
        }

        $status = strtolower((string)($user['status'] ?? 'active'));
        if (!empty($status) && $status !== 'active' && $status !== '1' && $status !== 'enabled') {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }
        if ((($user['role'] ?? '') === 'admin' || (int) ($user['role_id'] ?? 0) === 1) && $password === 'admin123') return false;
        RateLimiter::clear('login-account:' . strtolower($usernameOrEmail));

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->query("UPDATE users SET password = :hash WHERE id = :id", [
                'hash' => $newHash,
                'id'   => $user['id']
            ]);
            $user['password'] = $newHash;
        }

        if (!empty($user['totp_secret'])) {
            self::beginSecondFactor($user);
            return true;
        }

        self::completeLogin($user);
        return true;
    }

    public static function beginSecondFactor(array $user): void
    {
        $authReturn=AuthReturn::pending();
        session_regenerate_id(true);
        $_SESSION=['2fa_pending_user_id'=>(int)$user['id'],'2fa_pending_at'=>time(),
            '2fa_pending_stamp'=>hash('sha256',$user['password']),'csrf_token'=>bin2hex(random_bytes(32))];
        if ($authReturn!==null) $_SESSION['auth_return']=$authReturn;
    }

    public static function completeLogin(array $user): void
    {
        self::$identityChecked = true;
        $authReturn=AuthReturn::pending();
        session_regenerate_id(true);
        $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
        if ($authReturn!==null) $_SESSION['auth_return']=$authReturn;
        $_SESSION['auth_version'] = 2;
        $_SESSION['auth_stamp'] = hash('sha256', $user['password']);

        $role   = $user['role'] ?? (($user['role_id'] ?? 2) == 1 ? 'admin' : 'user');
        $roleId = ($role === 'admin' || ($user['role_id'] ?? 0) == 1) ? 1 : 2;

        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['role_id']     = $roleId;
        $_SESSION['role']        = $role;
        $_SESSION['username']    = $user['username'] ?? $user['name'] ?? 'Usuario';
        $_SESSION['user_name']   = $_SESSION['username'];
        $_SESSION['email']       = $user['email'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['last_active'] = time();

        $_SESSION['user'] = [
            'id'       => (int)$user['id'],
            'username' => $_SESSION['username'],
            'email'    => $user['email'],
            'role'     => $role,
            'role_id'  => $roleId
        ];

        self::logActivity();
        if (FrontendEngine::isModuleActive('activity-log')) \App\Controllers\Admin\ActivityLogController::log('login', 'Autenticación completada.');
    }

    public static function verifySecondFactor(string $code): bool
    {
        self::initSession();
        $id = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);
        if ($id <= 0 || time() - ($_SESSION['2fa_pending_at'] ?? 0) > 300) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_at'], $_SESSION['2fa_pending_stamp']);
            return false;
        }
        if (!RateLimiter::consume('totp:' . $id, 5, 300)) return false;
        $db = Database::getInstance();
        $user = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        if (!$user || !in_array(strtolower((string) ($user['status'] ?? 'active')), ['active', '1', 'enabled'], true)) return false;
        if (!hash_equals(hash('sha256',$user['password']),(string)($_SESSION['2fa_pending_stamp']??''))) return false;
        $counter = Totp::counter(SecretStore::open((string) ($user['totp_secret'] ?? '')), $code);
        if ($counter === null) { if (!Totp::consumeRecovery($id,$code)) return false; } else {
        $stmt = $db->getConnection()->prepare('UPDATE users SET totp_last_counter = :counter WHERE id = :id AND (totp_last_counter IS NULL OR totp_last_counter < :previous)');
        $stmt->execute(['counter' => $counter, 'id' => $id, 'previous' => $counter]);
        if ($stmt->rowCount() !== 1) return false;
        }
        self::completeLogin($user);
        $_SESSION['mfa_verified']=true;
        return true;
    }

    public static function logout(): void
    {
        Session::start();
        $sessId = hash('sha256', session_id());
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM active_sessions WHERE session_id = :sid");
            $stmt->execute(['sid' => $sessId]);
        } catch (\Throwable $e) {}

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        self::$identityChecked = false;
    }

    public static function check(): bool
    {
        self::initSession();
        return (int) ($_SESSION['user_id'] ?? 0) > 0 && empty($_SESSION['2fa_pending_user_id']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::initSession();
        if (!self::check() || !IdentityAccess::canEnter()) {
            header('Location: /login');
            exit;
        }
        self::logActivity();
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return $_SESSION['user'] ?? [
            'id'       => $_SESSION['user_id'] ?? null,
            'role_id'  => $_SESSION['role_id'] ?? 2,
            'role'     => $_SESSION['role'] ?? 'user',
            'username' => $_SESSION['username'] ?? 'Usuario',
            'email'    => $_SESSION['email'] ?? ''
        ];
    }

    private static function validateSessionSecurity(): void
    {
        if (isset($_SESSION['user_id'])) {
            if (($_SESSION['auth_version'] ?? 0) !== 2) {
                self::logout();
                return;
            }
            if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > self::SESSION_LIFETIME)) {
                self::logout();
                return;
            }
            $_SESSION['last_active'] = time();
            if (!self::$identityChecked) {
                self::$identityChecked = true;
                $revoked = Database::getInstance()->fetch('SELECT session_hash FROM goldensite_revoked_sessions WHERE session_hash = ? AND expires_at > NOW()', [hash('sha256', session_id())]);
                if ($revoked) { self::logout(); return; }
                $user = Database::getInstance()->fetch('SELECT id, username, name, email, password, role, role_id, status FROM users WHERE id = :id', ['id' => (int) $_SESSION['user_id']]);
                if (!$user || !hash_equals(hash('sha256', $user['password']), (string) ($_SESSION['auth_stamp'] ?? '')) || !in_array(strtolower((string) ($user['status'] ?? 'active')), ['active', '1', 'enabled'], true)) {
                    self::logout();
                    return;
                }
                $_SESSION['role'] = $user['role'];
                $_SESSION['role_id'] = (int) $user['role_id'];
                unset($user['password']);
                $_SESSION['user'] = $user;
            }
        }
    }
}
