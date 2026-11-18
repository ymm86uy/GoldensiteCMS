<?php
namespace App\Core;
final class AuthReturn
{
    private const PATHS = ['/chat','/reservas','/academia','/eventos','/dating','/dating?tab=profile','/marketplace','/marketplace?tab=seller','/marketplace?tab=cart','/marketplace?tab=orders'];

    public static function capture(mixed $value): void
    {
        Session::start();
        if (is_string($value)&&in_array($value,self::PATHS,true)) $_SESSION['auth_return']=['path'=>$value,'until'=>time()+600];
    }
    public static function destination(): string
    {
        $return=self::pending(); unset($_SESSION['auth_return']);
        if ($return!==null) return $return['path'];
        return IdentityAccess::canEnter()?AdminPath::url('/admin'):'/mi-cuenta';
    }
    public static function pending(): ?array
    {
        $return=$_SESSION['auth_return']??null;
        return is_array($return)&&($return['until']??0)>=time()&&in_array($return['path']??null,self::PATHS,true)?$return:null;
    }
}
