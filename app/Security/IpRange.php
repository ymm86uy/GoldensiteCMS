<?php
namespace App\Security;
final class IpRange
{
    public static function valid(string $value): bool
    {
        $parts = explode('/', $value);
        if (count($parts) > 2 || !filter_var($parts[0], FILTER_VALIDATE_IP)) return false;
        $bits = strlen(inet_pton($parts[0])) * 8;
        return !isset($parts[1]) || (ctype_digit($parts[1]) && (int)$parts[1] <= $bits);
    }
    public static function contains(string $range, string $ip): bool
    {
        if (!self::valid($range) || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
        [$network,$mask] = array_pad(explode('/', $range), 2, null);
        $a = inet_pton($network); $b = inet_pton($ip);
        if (strlen($a) !== strlen($b)) return false;
        $bits = $mask === null ? strlen($a)*8 : (int)$mask;
        $bytes = intdiv($bits,8); $remaining = $bits % 8;
        if (substr($a,0,$bytes) !== substr($b,0,$bytes)) return false;
        return !$remaining || ((ord($a[$bytes]) ^ ord($b[$bytes])) & (255 << (8-$remaining))) === 0;
    }
}
