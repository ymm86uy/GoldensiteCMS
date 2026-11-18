<?php
namespace App\Core;
final class WebhookEngine
{
    public const EVENTS=['post_published'=>'Publicación de artículos','page_published'=>'Publicación de páginas','contact_received'=>'Consultas recibidas','user_registered'=>'Nuevas cuentas','waf_blocked'=>'Bloqueos del WAF'];
    public static function config(): array { $settings=Settings::all(); return ['enabled'=>($settings['hooks_enabled']??'0')==='1','provider'=>$settings['hooks_provider']??'discord','url'=>SecretStore::open($settings['hooks_url']??''),'secret'=>SecretStore::open($settings['hooks_secret']??''),'events'=>json_decode($settings['hooks_events']??'[]',true)?:[],'chat'=>$settings['hooks_chat']??'']; }
    public static function url(string $url,string $provider): string {
        $parts=EnterpriseOidc::url($url); if (!in_array($provider,['discord','slack','telegram','json'],true)) throw new \InvalidArgumentException('Proveedor desconocido.'); $host=strtolower($parts['host']); $path=$parts['path']??'';
        if ($provider==='discord'&&(!in_array($host,['discord.com','discordapp.com'],true)||!preg_match('~^/api(?:/v\d+)?/webhooks/[0-9]+/[A-Za-z0-9_.-]+$~D',$path))) throw new \InvalidArgumentException('Usa la URL oficial del webhook de Discord.');
        if ($provider==='slack'&&($host!=='hooks.slack.com'||!preg_match('~^/services/[A-Za-z0-9/_-]+$~D',$path))) throw new \InvalidArgumentException('Usa una URL de incoming webhook de Slack.');
        if ($provider==='telegram'&&($host!=='api.telegram.org'||!preg_match('~^/bot[0-9]+:[A-Za-z0-9_-]+/sendMessage$~D',$path))) throw new \InvalidArgumentException('Usa la URL sendMessage de tu bot de Telegram.');
        if (isset($parts['query'])) throw new \InvalidArgumentException('Configura el endpoint sin parámetros de consulta.'); return $url;
    }
    public static function fingerprint(array $c): string { return hash('sha256',implode('|',[$c['provider'],$c['url'],$c['secret'],$c['chat']])); }
    public static function payload(string $provider,string $event,string $message,string $id,string $time,string $chat=''): array {
        $message=mb_substr(strip_tags($message),0,1800); $title='Goldensite · '.(self::EVENTS[$event]??'Prueba de conexión');
        return match($provider) { 'discord'=>['username'=>'GoldenSiteCMS','allowed_mentions'=>['parse'=>[]],'embeds'=>[['title'=>$title,'description'=>$message,'color'=>12971881,'timestamp'=>$time,'footer'=>['text'=>$id]]]],'slack'=>['text'=>$title."\n".strtr($message,['&'=>'&amp;','<'=>'&lt;','>'=>'&gt;'])."\n".$id,'unfurl_links'=>false,'unfurl_media'=>false],'telegram'=>['chat_id'=>$chat,'text'=>$title."\n".$message."\n".$id,'link_preview_options'=>['is_disabled'=>true]],default=>['id'=>$id,'event'=>$event,'occurred_at'=>$time,'message'=>$message] };
    }
    public static function enqueue(string $event,string $message,bool $test=false): ?string {
        $c=self::config(); if (!$c['enabled']||!FrontendEngine::isModuleActive('webhooks')||$c['url']===''||(!$test&&!in_array($event,$c['events'],true))) return null;
        self::url($c['url'],$c['provider']); if (!RateLimiter::consume('webhook-queue:'.$event,$event==='waf_blocked'?15:60,60)) return null; $id=bin2hex(random_bytes(16)); $body=json_encode(self::payload($c['provider'],$event,$message,$id,gmdate('c'),$c['chat']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        Database::getInstance()->execute("INSERT INTO goldensite_webhook_deliveries (id,event_name,payload_json,config_stamp,state) VALUES (?,?,?,?,'pending')",[$id,$event,$body,self::fingerprint($c)]); return $id;
    }
    public static function deliver(string $id,?callable $transport=null): array {
        if ($transport!==null&&PHP_SAPI!=='cli') throw new \LogicException('El transporte de pruebas solo se admite desde CLI.');
        $c=self::config(); $db=Database::getInstance(); $row=$db->fetch('SELECT * FROM goldensite_webhook_deliveries WHERE id=?',[$id]); if (!$row) throw new \InvalidArgumentException('Envío inexistente.');
        if (!$c['enabled']||!FrontendEngine::isModuleActive('webhooks')) return ['state'=>'paused','http_code'=>null];
        if (!hash_equals($row['config_stamp'],self::fingerprint($c))) { $db->execute("UPDATE goldensite_webhook_deliveries SET state='cancelled',last_error='Configuración modificada' WHERE id=?",[$id]); return ['state'=>'cancelled','http_code'=>null]; }
        $db->execute("UPDATE goldensite_webhook_deliveries SET state='processing',attempts=attempts+1,claimed_at=NOW() WHERE id=? AND state IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW())",[$id]);
        if ($db->getConnection()->query('SELECT ROW_COUNT()')->fetchColumn()!=1) return ['state'=>$row['state'],'http_code'=>$row['http_code']];
        $attempt=(int)$row['attempts']+1;
        $timestamp=(string)time();
        $headers=['X-Goldensite-Event-ID: '.$id,'X-Goldensite-Timestamp: '.$timestamp];
        if ($c['provider']==='json') {
            // Keep existing JSON receivers compatible during the brand change.
            if ($c['secret']!=='') {
                $signature='sha256='.hash_hmac('sha256',$timestamp.'.'.$row['payload_json'],$c['secret']);
                $headers[]='X-Goldensite-Signature: '.$signature;
            }
        }
        $url=$c['url'].($c['provider']==='discord'?'?wait=true':'');
        try { $r=$transport?$transport($url,$row['payload_json'],$headers):SafeHttp::postJsonResponse($url,$row['payload_json'],$headers); } catch (\Throwable $e) { $r=['status'=>0,'body'=>'','retry_after'=>null,'duration_ms'=>0]; }
        $code=(int)($r['status']??0); $ok=$code>=200&&$code<300;
        if ($ok&&$c['provider']==='slack') $ok=trim($r['body']??'')==='ok';
        if ($ok&&$c['provider']==='telegram') $ok=(json_decode($r['body']??'',true)['ok']??false)===true;
        if ($ok&&$c['provider']==='discord') $ok=is_string(json_decode($r['body']??'',true)['id']??null);
        $retry=!$ok&&($code===0||$code===429||$code>=500)&&$attempt<5; $state=$ok?'delivered':($retry?'retry':'failed'); $delay=max(10,min(3600,(int)($r['retry_after']??(30*2**($attempt-1)))));
        $db->execute('UPDATE goldensite_webhook_deliveries SET state=?,http_code=?,duration_ms=?,last_error=?,next_attempt_at=IF(?=1,DATE_ADD(NOW(),INTERVAL '.$delay.' SECOND),NULL),delivered_at=IF(?=1,NOW(),NULL) WHERE id=?',[$state,$code?:null,(int)($r['duration_ms']??0),$ok?null:($code?'El receptor no confirmó el envío.':'No se completó una conexión HTTPS pública verificada.'),$retry?1:0,$ok?1:0,$id]);
        return ['state'=>$state,'http_code'=>$code?:null];
    }
    public static function work(int $limit=20,?callable $transport=null): int { $db=Database::getInstance(); $db->execute("UPDATE goldensite_webhook_deliveries SET state='failed',last_error='Se agotó el límite de intentos' WHERE state='processing' AND claimed_at<NOW()-INTERVAL 5 MINUTE AND attempts>=5"); $db->execute("UPDATE goldensite_webhook_deliveries SET state='retry',next_attempt_at=NOW() WHERE state='processing' AND claimed_at<NOW()-INTERVAL 5 MINUTE AND attempts<5"); $rows=$db->fetchAll("SELECT id FROM goldensite_webhook_deliveries WHERE state IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) AND attempts<5 ORDER BY created_at LIMIT ".max(1,min(100,$limit))); foreach ($rows as $row) self::deliver($row['id'],$transport); return count($rows); }
}
