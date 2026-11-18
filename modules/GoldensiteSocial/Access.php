<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

use App\Core\{Auth,Environment,FrontendEngine,IdentityAccess,RateLimiter,RequestGuard,Settings};

final class Access
{
    public static function json(array $data,int $status=200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function enter(bool $write=false,bool $admin=false,bool $requirePublisher=true): ?int
    {
        header('Cache-Control: no-store, private');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        if (!FrontendEngine::isModuleActive('social-network')) { self::json(['error'=>'Módulo desactivado.'],404); return null; }
        $id=Auth::id();
        if (!$id) { self::json(['error'=>'Inicia sesión para entrar.'],401); return null; }
        if (($admin && !IdentityAccess::allows('social.moderate')) || (!$admin && $write && $requirePublisher && Settings::get('social_writers','members')==='roles'
            && !IdentityAccess::allows('social.publish') && !IdentityAccess::allows('social.moderate'))) {
            self::json(['error'=>'Tu rol no autoriza esta acción.'],403); return null;
        }
        if ($write) {
            $origin=$_SERVER['HTTP_ORIGIN']??'';
            if ($origin!=='' && $origin!==rtrim(Environment::get('APP_URL'),'/')) { self::json(['error'=>'Origen de la solicitud inválido.'],403); return null; }
            RequestGuard::post();
        }
        $bucket=$write?'write':'read';
        if (!RateLimiter::consume('social:'.$bucket.':user:'.$id,$write?25:90,60)
            || !RateLimiter::consume('social:'.$bucket.':ip:'.($_SERVER['REMOTE_ADDR']??''),$write?100:360,60)) {
            header('Retry-After: 60'); self::json(['error'=>'Espera un minuto antes de continuar.'],429); return null;
        }
        return $id;
    }

    public static function field(string $key,int $limit,bool $required=true): string
    {
        $value=$_POST[$key]??'';
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$limit
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value) || ($required && trim($value)==='')) {
            throw new \InvalidArgumentException('Revisa el campo '.$key.'.');
        }
        return trim($value);
    }

    public static function region(string $region): string
    {
        if (!array_key_exists($region,self::regions())) throw new \InvalidArgumentException('Selecciona una región del mapa.');
        return $region;
    }

    public static function regions(): array
    {
        static $regions;
        if ($regions!==null) return $regions;
        $map=json_decode(file_get_contents(dirname(__DIR__,2).'/public/assets/admin/world-map.json'),true,16,JSON_THROW_ON_ERROR);
        return $regions=$map['positions'];
    }
}

