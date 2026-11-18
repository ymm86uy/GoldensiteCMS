<?php
namespace App\Core;

final class PasswordRecovery
{
    public static function request(mixed $input): void
    {
        if (!is_string($input) || strlen($input)>150) return;
        $email=strtolower(trim($input));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !RateLimiter::consume('reset-email:'.$email,3,1800)
            || !RateLimiter::consume('reset-global',60,60)) return;
        ModuleJobs::enqueue('account','password_reset',bin2hex(random_bytes(16)),['email'=>$email,'requested'=>time()]);
    }

    public static function deliver(array $payload,?callable $transport=null): ?bool
    {
        if (($payload['requested']??0)<time()-900) return null;
        $base=rtrim(Environment::get('APP_URL'),'/');
        $url=parse_url($base);
        if (!filter_var($base,FILTER_VALIDATE_URL) || ($url['scheme']??null)!=='https' || isset($url['pass'])
            || isset($url['user']) || isset($url['query']) || isset($url['fragment'])) return null;
        $db=Database::getInstance();
        $user=$db->fetch('SELECT id,email,password FROM users WHERE email=? AND status IN (\'active\',\'enabled\',\'1\')',[$payload['email']]);
        if (!$user) return null;
        $token=bin2hex(random_bytes(32));
        $digest=hash('sha256',$token);
        $db->execute('INSERT INTO goldensite_password_resets (user_id,token_hash,account_stamp,expires_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),account_stamp=VALUES(account_stamp),expires_at=VALUES(expires_at)',
            [$user['id'],$digest,self::stamp($user),time()+1800]);
        $mail=['to'=>$user['email'],'subject'=>'Restablece tu contraseña','body'=>"Abre este enlace para elegir una contraseña nueva. Caduca en 30 minutos y solo funciona una vez.\n\n".$base.'/login/reset?token='.$token."\n\nSi no solicitaste este cambio, puedes ignorar este mensaje."];
        $sent=$transport?(bool)$transport('email',$mail):MailTransport::send($mail['to'],$mail['subject'],$mail['body']);
        if (!$sent) $db->execute('DELETE FROM goldensite_password_resets WHERE user_id=? AND token_hash=?',[$user['id'],$digest]);
        return $sent;
    }

    public static function validToken(mixed $token): bool
    {
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D',$token)===1;
    }

    public static function reset(string $token,mixed $password,mixed $confirmation): bool
    {
        if (!self::validToken($token)) return false;
        if (!is_string($password) || $password!==$confirmation || strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) {
            throw new \InvalidArgumentException('Introduce dos contraseñas iguales de 12 a 72 bytes.');
        }
        $db=Database::getInstance();
        $digest=hash('sha256',$token);
        $candidate=$db->fetch('SELECT user_id FROM goldensite_password_resets WHERE token_hash=? AND expires_at>?',[$digest,time()]);
        if (!$candidate) return false;
        $pdo=$db->getConnection();
        $pdo->beginTransaction();
        try {
            $user=$db->fetch("SELECT id,email,password FROM users WHERE id=? AND status IN ('active','enabled','1') FOR UPDATE",[$candidate['user_id']]);
            $reset=$db->fetch('SELECT account_stamp FROM goldensite_password_resets WHERE user_id=? AND token_hash=? AND expires_at>? FOR UPDATE',[$candidate['user_id'],$digest,time()]);
            if (!$user || !$reset || !hash_equals($reset['account_stamp'],self::stamp($user))) { $pdo->rollBack(); return false; }
            $db->execute('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$user['id']]);
            $db->execute('DELETE FROM goldensite_password_resets WHERE user_id=?',[$user['id']]);
            $db->execute('DELETE FROM active_sessions WHERE user_id=?',[$user['id']]);
            $pdo->commit();
        } catch (\Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        MailTransport::send($user['email'],'Contraseña actualizada','Se restableció tu contraseña y se cerraron las sesiones anteriores. Si no reconoces el cambio, contacta con la administración.');
        return true;
    }

    private static function stamp(array $user): string
    {
        return hash('sha256',$user['email'].'|'.$user['password']);
    }
}
