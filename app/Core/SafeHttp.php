<?php
namespace App\Core;
final class SafeHttp
{
    public static function publicResponseHeaders(string $url): array
    {
        [$host,$ip]=self::publicTarget($url,'El dominio configurado requiere una dirección pública.','No se pudo resolver el dominio configurado.');
        $response=[]; $bytes=0; $discarded=false;
        $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,
            CURLOPT_RESOLVE=>[$host.':443:'.(str_contains($ip,':')?'['.$ip.']':$ip)],
            CURLOPT_WRITEFUNCTION=>static function () use (&$discarded): int { $discarded=true; return 0; },
            CURLOPT_HEADERFUNCTION=>static function ($handle,string $line) use (&$response,&$bytes): int {
                $bytes+=strlen($line);
                if ($bytes>16384) return 0;
                if (str_starts_with($line,'HTTP/')) $response=[];
                if (preg_match('/^([a-z0-9-]+):\s*(.*)$/Di',rtrim($line,"\r\n"),$match)) $response[strtolower($match[1])]=trim($match[2]);
                return strlen($line);
            },
        ]);
        try {
            $ok=curl_exec($curl);
            if ($ok===false && !($discarded && curl_errno($curl)===CURLE_WRITE_ERROR)) throw new \RuntimeException('La solicitud HTTPS no se completó.');
            return ['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'headers'=>$response];
        } finally { curl_close($curl); }
    }

    public static function getJsonResponse(string $url, array $headers, int $timeout): array
    {
        [$host,$ip]=self::publicTarget($url,'La URL de Joomla debe resolver únicamente a direcciones públicas.','No se encontró el dominio público de Joomla.');
        foreach ($headers as $header) if (preg_match('/[\r\n]/',$header)) throw new \InvalidArgumentException('Encabezado HTTP inválido.');

        $body='';
        $curl=curl_init($url);
        curl_setopt_array($curl,[
            CURLOPT_HTTPHEADER=>$headers, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROXY=>'', CURLOPT_CONNECTTIMEOUT=>min(4,$timeout), CURLOPT_TIMEOUT=>$timeout,
            CURLOPT_RESOLVE=>[$host.':443:'.(str_contains($ip,':')?'['.$ip.']':$ip)],
            CURLOPT_WRITEFUNCTION=>static function($handle,$chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>4194304) return 0;
                $body.=$chunk;
                return strlen($chunk);
            }
        ]);
        try {
            if (curl_exec($curl)===false) throw new \InvalidArgumentException('No se completó la conexión HTTPS con Joomla. Comprueba el certificado, la disponibilidad y el tamaño de la respuesta.');
            return ['status'=>(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),'body'=>$body];
        } finally {
            curl_close($curl);
        }
    }

    private static function publicTarget(string $url,string $privateMessage,string $dnsMessage): array
    {
        $parts=EnterpriseOidc::url($url);
        $host=$parts['host'];
        $addresses=[];
        // An absolute DNS name prevents the resolver from appending its search suffix.
        $dnsName=rtrim($host,'.').'.';
        foreach (dns_get_record($dnsName,DNS_A|DNS_AAAA)?:[] as $record) {
            $ip=$record['ip']??$record['ipv6']??null;
            if ($ip===null) continue;
            if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE) || !self::isPublicIp($ip)) {
                throw new \InvalidArgumentException($privateMessage);
            }
            $addresses[]=$ip;
        }
        if (!$addresses) throw new \InvalidArgumentException($dnsMessage);
        return [$host,$addresses[0]];
    }

    public static function postJson(string $url,array $payload): bool { $r=self::postJsonResponse($url,json_encode($payload,JSON_THROW_ON_ERROR)); return $r['status']>=200&&$r['status']<300; }
    public static function postJsonResponse(string $url,string $json,array $headers=[]): array {
        $failure=['status'=>0,'body'=>'','retry_after'=>null,'duration_ms'=>0];
        try { $parts=EnterpriseOidc::url($url); } catch (\InvalidArgumentException $e) { return $failure; }
        $host=$parts['host']; $addresses=[]; foreach (dns_get_record($host,DNS_A|DNS_AAAA)?:[] as $record) { $ip=$record['ip']??$record['ipv6']??null; if (!$ip) continue; if (!self::isPublicIp($ip)) return $failure; $addresses[]=$ip; } if (!$addresses||strlen($json)>65536) return $failure;
        foreach ($headers as $header) if (!is_string($header)||preg_match('/[\r\n]/',$header)) return $failure;
        $body=''; $retry=null; $ip=$addresses[0]; $curl=curl_init($url); $headers[]='Content-Type: application/json';
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.(str_contains($ip,':')?'['.$ip.']':$ip)],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use (&$body) { if (strlen($body)+strlen($chunk)>65536) return 0; $body.=$chunk; return strlen($chunk); },CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$retry) { if (preg_match('/^Retry-After:\s*(\d{1,5})/i',$line,$m)) $retry=min(3600,(int)$m[1]); return strlen($line); }]);
        try { $ok=curl_exec($curl); return ['status'=>$ok?(int)curl_getinfo($curl,CURLINFO_HTTP_CODE):0,'body'=>$ok?$body:'','retry_after'=>$retry,'duration_ms'=>(int)round(curl_getinfo($curl,CURLINFO_TOTAL_TIME)*1000)]; } finally { curl_close($curl); }
    }
    public static function isPublicIp(string $ip): bool { if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false; if (str_contains($ip,':')) return str_starts_with(strtolower($ip),'2')||str_starts_with(strtolower($ip),'3'); $long=ip2long($ip); return ($long&0xffc00000)!==ip2long('100.64.0.0')&&($long&0xffff0000)!==ip2long('169.254.0.0'); }
}
