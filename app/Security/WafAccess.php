<?php
declare(strict_types=1);
namespace App\Security;

use App\Core\{Database,Environment};

final class WafAccess
{
    public const AUTOMATIC='Goldensite WAF automático';

    public static function address(string $ip): string
    {
        if (!filter_var($ip,FILTER_VALIDATE_IP)) return '';
        $binary=inet_pton($ip);
        if (strlen($binary)===16 && str_starts_with($binary,str_repeat("\0",10)."\xff\xff")) {
            $binary=substr($binary,12);
        }
        return inet_ntop($binary);
    }

    public static function allowedToAutoban(string $ip,bool $staff): bool
    {
        if ($staff || !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE)) return false;
        foreach (explode(',',Environment::get('GOLDENSITE_WAF_AUTOBAN_EXEMPT')) as $range) {
            if (IpRange::contains(trim($range),$ip)) return false;
        }
        return true;
    }

    public static function blocked(Database $db,string $ip,string $original): bool
    {
        if ($ip==='') return false;
        $active='(expires_at IS NULL OR expires_at>NOW())';
        if ($db->fetch("SELECT id FROM goldensite_waf_blacklist WHERE ip_address IN (?,?) AND $active LIMIT 1",[$ip,$original])) return true;
        $filter=str_contains($ip,':')
            ? "(ip_address LIKE '%/%' OR (ip_address LIKE '%:%' AND COALESCE(username,'')<>?))"
            : "ip_address LIKE '%/%'";
        $params=str_contains($ip,':')?[self::AUTOMATIC]:[];
        foreach ($db->fetchAll("SELECT ip_address FROM goldensite_waf_blacklist WHERE $active AND $filter",$params) as $rule) {
            if (IpRange::contains($rule['ip_address'],$ip) || ($original!==$ip && IpRange::contains($rule['ip_address'],$original))) return true;
        }
        return false;
    }

    public static function permanentRules(array $ids,bool $blockMode): array
    {
        $certain=array_intersect($ids,['NX-100','NX-110','NX-500']);
        if ($blockMode) {
            $patterns=array_intersect($ids,['NX-200','NX-210','NX-220','NX-230','NX-240']);
            if (count($patterns)>=2) $certain=array_merge($certain,$patterns);
        }
        return array_values(array_unique($certain));
    }

    public static function addAutomatic(Database $db,string $ip,array $ids,string $reference): bool
    {
        $reason='Automático · '.implode(', ',$ids).' · Ref. '.$reference;
        $query=$db->getConnection()->prepare('INSERT INTO goldensite_waf_blacklist (ip_address,username,reason,expires_at)
            VALUES (?,?,?,NULL) ON DUPLICATE KEY UPDATE
            username=IF(expires_at<=NOW(),VALUES(username),username),
            reason=IF(expires_at<=NOW(),VALUES(reason),reason),
            created_at=IF(expires_at<=NOW(),NOW(),created_at),
            expires_at=IF(expires_at<=NOW(),NULL,expires_at)');
        $query->execute([$ip,self::AUTOMATIC,$reason]);
        return $query->rowCount()>0;
    }
}
