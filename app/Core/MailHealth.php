<?php
declare(strict_types=1);
namespace App\Core;

final class MailHealth
{
    private static function fingerprint(): string
    {
        $values=[];
        foreach (['smtp_host','smtp_port','smtp_crypto','smtp_user','smtp_pass','admin_email'] as $key) $values[]=Settings::get($key);
        return hash_hmac('sha256',json_encode($values,JSON_THROW_ON_ERROR),KeyStore::vault());
    }

    public static function record(string $kind,bool $ok,string $reference=''): void
    {
        self::key($kind);
        if ($kind==='delivery' && !preg_match('/^[A-F0-9]{8}$/D',$reference)) throw new \InvalidArgumentException('Referencia de prueba inválida.');
        Settings::set(self::key($kind),json_encode(['time'=>time(),'ok'=>$ok,'reference'=>$kind==='delivery'?$reference:'',
            'confirmed_at'=>null,'configuration'=>self::fingerprint()],JSON_THROW_ON_ERROR));
    }

    public static function status(string $kind): ?array
    {
        $data=json_decode(Settings::get(self::key($kind),''),true);
        if (!is_array($data) || !is_int($data['time']??null) || $data['time']<1 || $data['time']>time()
            || !is_bool($data['ok']??null) || !is_string($data['configuration']??null)
            || !is_string($data['reference']??null) || ($kind==='delivery' && !preg_match('/^[A-F0-9]{8}$/D',$data['reference']))) return null;
        $confirmed=$data['confirmed_at']??null;
        if ($confirmed!==null && (!is_int($confirmed) || $confirmed<$data['time'] || $confirmed>time())) return null;
        return ['time'=>$data['time'],'ok'=>$data['ok'],'reference'=>$data['reference'],'confirmed_at'=>$confirmed,
            'current'=>hash_equals(self::fingerprint(),$data['configuration'])];
    }

    public static function confirm(string $reference): void
    {
        $raw=Settings::get(self::key('delivery'), '');
        $status=self::status('delivery');
        if (!$status || !$status['current'] || !$status['ok'] || time()-$status['time']>86400
            || !hash_equals($status['reference'],$reference)) throw new \InvalidArgumentException('La prueba cambió, no fue aceptada o venció. Envía otra prueba y confirma su referencia.');
        $data=json_decode($raw,true,8,JSON_THROW_ON_ERROR); $data['confirmed_at']=time();
        $query=Database::getInstance()->getConnection()->prepare('UPDATE settings SET setting_value=? WHERE setting_key=? AND setting_value=?');
        $query->execute([json_encode($data,JSON_THROW_ON_ERROR),self::key('delivery'),$raw]);
        if ($query->rowCount()!==1) throw new \InvalidArgumentException('La prueba cambió. Recarga para comprobar la referencia actual.');
        Settings::reset();
    }

    private static function key(string $kind): string
    {
        if (!in_array($kind,['connection','delivery'],true)) throw new \InvalidArgumentException('Comprobación de correo inválida.');
        return 'mail_health_'.$kind;
    }
}
