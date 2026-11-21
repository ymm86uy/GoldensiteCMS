<?php
declare(strict_types=1);
namespace App\Security;

use App\Core\{AdminPath,Database,Environment,RateLimiter,Settings};

/** Local, bounded observation; browser reports are untrusted compatibility signals. */
final class CspReports
{
    public const PATH='/_security/csp-report';
    private const DIRECTIVES=['script-src','script-src-elem','script-src-attr','object-src','base-uri','frame-ancestors'];

    public static function enabled(): bool { return Settings::get('csp_observe','0')==='1'; }

    private static function origin(string $url): ?string
    {
        if (strlen($url)>4096 || preg_match('/[\x00-\x20\x7f]/',$url)) return null;
        $p=parse_url($url);
        if (!is_array($p) || !in_array($p['scheme']??'', ['http','https'],true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return null;
        $host=strtolower($p['host']); $address=trim($host,'[]');
        if (!filter_var($address,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) return null;
        $port=$p['port']??($p['scheme']==='https'?443:80);
        return $p['scheme'].'://'.$host.(($p['scheme']==='https' && $port===443)||($p['scheme']==='http' && $port===80)?'':':'.$port);
    }

    public static function siteOrigin(): ?string
    {
        $url=Environment::get('APP_URL'); $p=parse_url($url);
        if (!is_array($p) || !in_array($p['path']??'', ['','/'],true) || isset($p['query']) || isset($p['fragment'])) return null;
        return self::origin($url);
    }

    public static function headers(): void
    {
        if (!self::enabled() || !($origin=self::siteOrigin()) || ($_SERVER['REQUEST_METHOD']??'GET')!=='GET') return;
        header('Reporting-Endpoints: goldensite-csp="'.$origin.self::PATH.'"');
        header("Content-Security-Policy-Report-Only: script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; report-to goldensite-csp; report-uri ".self::PATH);
    }

    /** Convert both standard formats into a finite vocabulary before any storage. */
    public static function normalize(string $raw,string $type,string $origin): array
    {
        if (strlen($raw)>16384) throw new \InvalidArgumentException('Informe demasiado grande.');
        $data=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
        $modern=$type==='application/reports+json';
        $items=$modern?$data:[$data];
        if (!is_array($items) || !array_is_list($items) || !$items || count($items)>8) throw new \InvalidArgumentException('Lote inválido.');
        $rows=[];
        foreach ($items as $item) {
            if (!is_array($item) || ($modern && ($item['type']??null)!=='csp-violation')) throw new \InvalidArgumentException('Tipo de informe inválido.');
            $body=$modern?($item['body']??null):($item['csp-report']??null);
            if (!is_array($body) || ($body['disposition']??null)!=='report') throw new \InvalidArgumentException('Solo se admiten avisos de observación.');
            $url=$body[$modern?'documentURL':'document-uri']??null;
            $directive=$body[$modern?'effectiveDirective':'effective-directive']??null;
            $blocked=$body[$modern?'blockedURL':'blocked-uri']??null;
            if (!is_string($url) || self::origin($url)!==$origin || !is_string($directive) || !in_array($directive,self::DIRECTIVES,true) || !is_string($blocked) || strlen($blocked)>4096) throw new \InvalidArgumentException('Informe inválido.');
            $path=AdminPath::normalize(parse_url($url,PHP_URL_PATH)?:'/');
            $area=$path==='/admin'||str_starts_with($path,'/admin/')?'admin':(preg_match('~^/(?:login|register|registro|account|mi-cuenta)(?:/|$)~',$path)?'account':'public');
            $kind=in_array($blocked,['inline','eval'],true)?$blocked:(self::origin($blocked)===$origin?'self':(self::origin($blocked)!==null?'external':'other'));
            $key=$area.'|'.$directive.'|'.$kind;
            if (!isset($rows[$key])) $rows[$key]=['area'=>$area,'directive'=>$directive,'blocked_kind'=>$kind,'events'=>0];
            $rows[$key]['events']++;
        }
        return array_values($rows);
    }

    public static function receive(): void
    {
        header('Cache-Control: no-store');
        // This endpoint never needs an authenticated session or an administrative CSRF token.
        if (!self::enabled() || !($origin=self::siteOrigin())) { http_response_code(204); return; }
        $requestOrigin=$_SERVER['HTTP_ORIGIN']??null; $fetch=$_SERVER['HTTP_SEC_FETCH_SITE']??'';
        if (($requestOrigin!==null && $requestOrigin!==$origin) || !in_array($fetch,['','none','same-origin'],true)) { http_response_code(403); return; }
        $type=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
        if (!in_array($type,['application/csp-report','application/reports+json'],true)) { http_response_code(415); return; }
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>16384) { http_response_code(413); return; }
        try {
            if (!RateLimiter::consume('csp-reports:ip:'.($_SERVER['REMOTE_ADDR']??''),20,60) || !RateLimiter::consume('csp-reports:site',60,60)) { http_response_code(429); header('Retry-After: 60'); return; }
            $raw=file_get_contents('php://input',false,null,0,16385);
            if (!is_string($raw) || strlen($raw)>16384) { http_response_code(413); return; }
            $rows=self::normalize($raw,$type,$origin);
            $db=Database::getInstance()->getConnection();
            $db->beginTransaction();
            try {
                $q=$db->prepare('INSERT INTO goldensite_csp_reports (report_day,area,directive,blocked_kind,events,last_seen) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE events=LEAST(events+VALUES(events),1000000),last_seen=VALUES(last_seen)');
                foreach ($rows as $row) $q->execute([gmdate('Y-m-d'),$row['area'],$row['directive'],$row['blocked_kind'],$row['events'],gmdate('Y-m-d H:i:s')]);
                // Fixed vocabulary and rolling retention keep cardinality bounded, even without cron.
                self::prune();
                $db->commit();
            } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
            http_response_code(204);
        } catch (\InvalidArgumentException | \JsonException) { http_response_code(400); }
        catch (\Throwable) { error_log('CSP observation: local storage unavailable.'); http_response_code(503); }
    }

    public static function prune(): void
    {
        Database::getInstance()->getConnection()->exec('DELETE FROM goldensite_csp_reports WHERE report_day<DATE_SUB(UTC_DATE(),INTERVAL 13 DAY)');
    }

    public static function summary(): array
    {
        try {
            $db=Database::getInstance();
            $rows=$db->fetchAll('SELECT area,directive,blocked_kind,SUM(events) AS events,MAX(last_seen) AS last_seen FROM goldensite_csp_reports WHERE report_day>=DATE_SUB(UTC_DATE(),INTERVAL 13 DAY) GROUP BY area,directive,blocked_kind ORDER BY events DESC,area,directive,blocked_kind LIMIT 60');
            return ['available'=>true,'rows'=>$rows];
        } catch (\Throwable) { return ['available'=>false,'rows'=>[]]; }
    }
}
