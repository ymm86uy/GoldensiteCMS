<?php
namespace App\Security;
use App\Core\{AdminPath,Auth,Csrf,Database,RateLimiter,Settings};
final class WafGateway
{
    public static function configuration(): array
    {
        return ['mode'=>Settings::get('waf_mode','block')==='monitor'?'monitor':'block', 'limit'=>max(60,min(2000,(int)Settings::get('waf_request_limit','600'))), 'retention'=>max(7,min(90,(int)Settings::get('waf_retention_days','30')))];
    }
    public static function handle(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? ''; $ip=WafAccess::address($remote);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestId=bin2hex(random_bytes(16)); header('X-Goldensite-Request-ID: '.$requestId);
        $authenticated=false; $author=false; $staff=false;
        try { $authenticated=Auth::check(); $staff=$authenticated && \App\Core\IdentityAccess::canEnter(); $author=$authenticated && \App\Core\IdentityAccess::allows(\App\Core\IdentityAccess::permissionFor(parse_url($uri,PHP_URL_PATH)?:'/','POST')) && Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf_token']??null); } catch (\Throwable $e) {}
        $config=self::configuration();
        $hits=WafPolicy::inspect($method,$uri,$_GET,$_POST,$author,(int)($_SERVER['CONTENT_LENGTH']??0));
        $customHit=WafCustomRules::hit(strtoupper($method),$uri);
        $db=Database::getInstance();
        if (WafAccess::blocked($db,$ip,$remote)) {
            $hit=['id'=>'NX-400','field'=>'client']+WafPolicy::rules()['NX-400'];
            self::record($requestId,[$hit],'BLOCK',1);
            self::reject(403,$requestId);
        }
        $budget=$authenticated?max(600,$config['limit']*4):$config['limit'];
        if (!RateLimiter::consume('waf-rate:'.($authenticated?'session:':'public:').$ip,$budget,60)) $hits[]=['id'=>'NX-300','field'=>'client']+WafPolicy::rules()['NX-300'];
        if (!$hits && !$customHit) return;
        $mandatory=(bool)array_intersect(array_column($hits,'id'),['NX-100','NX-110','NX-120','NX-130','NX-300','NX-400']);
        $block=$mandatory || ($hits && $config['mode']==='block') || ($customHit['custom_block']??false); $status=in_array('NX-300',array_column($hits,'id'),true)?429:(in_array('NX-130',array_column($hits,'id'),true)?413:(in_array('NX-120',array_column($hits,'id'),true)?405:403));
        if ($customHit) $hits[]=$customHit;
        if ($block) self::autoban($requestId,array_column($hits,'id'),$ip,$staff,$config['mode']==='block');
        if (self::record($requestId,$hits,$block?'BLOCK':'MONITOR') && $block) \App\Controllers\Admin\WebhooksController::dispatch('waf_blocked','Solicitud bloqueada. Referencia: '.$requestId.' · Reglas: '.implode(',',array_column($hits,'id')));
        if (!$block) return;
        self::reject($status,$requestId);
    }

    public static function autoban(string $id,array $ids,string $ip,bool $staff,bool $blockMode): void
    {
        $rules=WafAccess::permanentRules($ids,$blockMode);
        if (!$rules || !WafAccess::allowedToAutoban($ip,$staff)) return;
        try { WafAccess::addAutomatic(Database::getInstance(),$ip,$rules,$id); }
        catch (\Throwable $error) { error_log('WAF automatic block: '.$error->getMessage()); }
    }

    private static function reject(int $status,string $requestId): never
    {
        http_response_code($status); header('Cache-Control: no-store'); if ($status===429) header('Retry-After: 60'); if ($status===405) header('Allow: GET, POST, HEAD, OPTIONS');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Solicitud protegida · Goldensite</title><body style="background:#101317;color:#eef2e9;font-family:Segoe UI,Arial;padding:10vh 8vw"><p style="color:#c5ef69;letter-spacing:3px;font-size:11px">GOLDENSITE / PROTECCIÓN</p><h1 style="font-size:38px;max-width:600px">'.($status===429?'Espera antes de continuar.':'Esta solicitud se ha detenido.').'</h1><p style="color:#abb3bf">'.($status===429?'Has alcanzado el límite temporal de solicitudes.':'Contacta con la administración si consideras que se trata de un error.').'</p><p style="font:12px monospace;color:#788392">Referencia '.$requestId.'</p><a href="/" style="color:#c5ef69">Volver al sitio ↗</a></body></html>'; exit;
    }
    public static function record(string $id,array $hits,string $action,int $budget=60): bool
    {
        try {
            $ip=WafAccess::address($_SERVER['REMOTE_ADDR']??'');
            $bucket=$budget===1?'waf-log-denied:':'waf-log:';
            if (!RateLimiter::consume($bucket.$ip,$budget,60)) return false;
            $db=Database::getInstance();
            $db->execute('INSERT INTO goldensite_waf_logs (ip_address, username, user_agent, method, target_url, vector, risk_score, request_id, rule_ids, disposition, matched_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ip,'Solicitud',substr($_SERVER['HTTP_USER_AGENT']??'',0,250),substr($_SERVER['REQUEST_METHOD']??'GET',0,10),substr(explode('?',$_SERVER['REQUEST_URI']??'/',2)[0],0,1024),substr(implode(' / ',array_column($hits,'category')),0,100),min(100,array_sum(array_column($hits,'score'))*10),$id,implode(',',array_column($hits,'id')),$action,substr(implode(', ',array_column($hits,'field')),0,255)]);
            return true;
        } catch (\Throwable $e) { error_log('WAF telemetry: '.$e->getMessage()); return false; }
    }
}
