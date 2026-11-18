<?php
namespace App\Core;
final class FtpsConnection
{
    public static function test(string $host,int $port,string $user,string $password): bool {
        if (!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||filter_var($host,FILTER_VALIDATE_IP)||$port<1||$port>65535||preg_match('/[\r\n\x00]/',$user.$password)) return false;
        $addresses=[]; foreach (dns_get_record($host,DNS_A|DNS_AAAA)?:[] as $record) { $ip=$record['ip']??$record['ipv6']??''; if (!$ip) continue; if (!SafeHttp::isPublicIp($ip)) return false; $addresses[]=$ip; } if (!$addresses) return false;
        $ip=$addresses[0]; $ch=curl_init('ftp://'.$host.':'.$port.'/'); curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_USERNAME=>$user,CURLOPT_PASSWORD=>$password,CURLOPT_PROTOCOLS=>CURLPROTO_FTP,CURLOPT_USE_SSL=>CURLUSESSL_ALL,CURLOPT_FTPSSLAUTH=>CURLFTPAUTH_TLS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_RESOLVE=>[$host.':'.$port.':'.(str_contains($ip,':')?'['.$ip.']':$ip)],CURLOPT_RETURNTRANSFER=>true]); try { return curl_exec($ch)!==false; } finally { curl_close($ch); }
    }
}
