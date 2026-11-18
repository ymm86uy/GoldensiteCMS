<?php
namespace App\Core;

final class AccountRegistration
{
    private const DRAFT = 'account_registration';
    public const CODE_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public static function pending(): bool
    {
        if (($_SESSION[self::DRAFT]['until'] ?? 0) > time()) return true;
        unset($_SESSION[self::DRAFT]);
        return false;
    }

    public static function begin(array $input, ?callable $send = null): bool
    {
        $username = is_string($input['username']??null) ? trim($input['username']) : '';
        $email = is_string($input['email']??null) ? strtolower(trim($input['email'])) : '';
        $password = $input['password']??null;
        if (strlen($username)>200 || !preg_match('/^[\p{L}\p{N}_.-]{3,50}$/uD',$username)
            || strlen($email)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($password)
            || strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) {
            throw new \InvalidArgumentException('Usa un nombre de 3 a 50 caracteres, un correo válido y una contraseña de 12 a 72 bytes.');
        }
        if ($password !== ($input['confirm_password']??null)) throw new \InvalidArgumentException('Las contraseñas no coinciden.');
        if ($send===null && (Settings::get('smtp_host')==='' || !filter_var(Settings::get('admin_email'),FILTER_VALIDATE_EMAIL))) {
            throw new \InvalidArgumentException('El registro por correo aún no está disponible. Contacta con la administración del sitio.');
        }
        self::limit($email);
        $_SESSION[self::DRAFT] = ['username'=>$username,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),
            'secret'=>bin2hex(random_bytes(32)),'until'=>time()+3600,'resend_after'=>0,'digest'=>'','expires'=>0,'attempts'=>0];
        return self::sendCode($send);
    }

    public static function resend(?callable $send = null): bool
    {
        if (!self::pending()) throw new \InvalidArgumentException('Vuelve a completar el formulario de registro.');
        $draft = $_SESSION[self::DRAFT];
        if ($draft['resend_after'] > time()) throw new \InvalidArgumentException('Espera al menos un minuto antes de solicitar otro código.');
        self::limit($draft['email']);
        return self::sendCode($send);
    }

    private static function limit(string $email): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::consume('registration:ip:'.$ip,8,1800) || !RateLimiter::consume('registration:email:'.$email,3,1800)
            || !RateLimiter::consume('registration:global',60,60)) {
            throw new \InvalidArgumentException('Se alcanzó el límite de envíos. Inténtalo más tarde.');
        }
    }

    private static function sendCode(?callable $send): bool
    {
        $draft = &$_SESSION[self::DRAFT];
        $draft['resend_after'] = time()+60;
        $db = Database::getInstance();
        if ($db->fetch('SELECT id FROM users WHERE email=? OR username=? LIMIT 1',[$draft['email'],$draft['username']])) return true;
        $secret = KeyStore::vault();
        do {
            $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            $digest = hash_hmac('sha256',$draft['secret'].':'.$code,$secret);
        } while (hash_equals($draft['digest'],$digest));
        $send ??= MailTransport::send(...);
        if (!$send($draft['email'],'Código de activación de tu cuenta',"Tu código de activación es $code.\n\nCaduca en ".self::CODE_MINUTES." minutos y solo se usa una vez. Introdúcelo en la página donde iniciaste el registro. Si no solicitaste una cuenta, puedes ignorar este mensaje.")) return false;
        $draft['digest'] = $digest;
        $draft['expires'] = time()+60*self::CODE_MINUTES;
        $draft['attempts'] = 0;
        return true;
    }

    public static function activate(mixed $code): ?array
    {
        if (!self::pending()) return null;
        $draft = &$_SESSION[self::DRAFT];
        if ($draft['attempts']>=self::MAX_ATTEMPTS || $draft['expires']<=time()) return null;
        $draft['attempts']++;
        if (!is_string($code) || !preg_match('/^[0-9]{6}$/D',$code)
            || !hash_equals($draft['digest'],hash_hmac('sha256',$draft['secret'].':'.$code,KeyStore::vault()))) return null;

        $account = ['username'=>$draft['username'],'email'=>$draft['email']];
        try {
            Database::getInstance()->execute("INSERT INTO users (username,name,email,password,role,role_id,status,created_at) VALUES (?,?,?,?,'user',2,'active',NOW())",
                [$account['username'],$account['username'],$account['email'],$draft['password_hash']]);
        } catch (\PDOException $error) {
            if ($error->getCode()!=='23000') throw $error;
            unset($_SESSION[self::DRAFT]);
            return null;
        }
        unset($_SESSION[self::DRAFT]);
        return $account;
    }
}
