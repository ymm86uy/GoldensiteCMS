<?php
namespace App\Core;

final class NewsletterService
{
    public static function subscribe(string $email,bool $consent): void
    {
        if (!FrontendEngine::isModuleActive('newsletter')||!$consent||strlen($email)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Introduce un correo válido y acepta recibir el boletín.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->execute("INSERT INTO goldensite_subscribers (email,status,consent_key,optin_requested_at) VALUES (?,'pending',?,UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE email=VALUES(email)",[$email,bin2hex(random_bytes(16))]);
            $subscriber=$db->fetch('SELECT * FROM goldensite_subscribers WHERE email=? FOR UPDATE',[$email]);
            if ($subscriber['status']==='active'&&!empty($subscriber['consented_at'])) { $pdo->commit(); return; }
            if ($subscriber['status']!=='pending'||empty($subscriber['consent_key'])||(int)$subscriber['optin_requested_at']<time()-86400) {
                $db->execute("UPDATE goldensite_subscribers SET status='pending',consent_key=?,optin_requested_at=UNIX_TIMESTAMP(),consented_at=NULL WHERE id=?",[bin2hex(random_bytes(16)),$subscriber['id']]);
                $subscriber=$db->fetch('SELECT * FROM goldensite_subscribers WHERE id=?',[$subscriber['id']]);
            }
            ModuleJobs::enqueue('newsletter','newsletter_confirm','confirm:'.$subscriber['id'].':'.$subscriber['consent_key'],['subscriber_id'=>(int)$subscriber['id'],'consent_key'=>$subscriber['consent_key']]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function token(array $subscriber,string $purpose): string
    {
        return hash_hmac('sha256','newsletter:'.$purpose.':'.$subscriber['id'].':'.$subscriber['consent_key'],KeyStore::vault());
    }
    public static function signedSubscriber(int $id,string $token,string $purpose): ?array
    {
        if (!in_array($purpose,['confirm','unsubscribe'],true)||$id<1||!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
        $row=Database::getInstance()->fetch('SELECT * FROM goldensite_subscribers WHERE id=?',[$id]);
        if (!$row||empty($row['consent_key'])||!hash_equals(self::token($row,$purpose),$token)) return null;
        if ($purpose==='confirm'&&($row['status']!=='pending'||(int)$row['optin_requested_at']<time()-172800)) return null;
        return $row;
    }
    public static function changeSubscription(int $id,string $token,string $purpose): void
    {
        if ($purpose==='confirm'&&!FrontendEngine::isModuleActive('newsletter')) throw new \InvalidArgumentException('El boletín está desactivado.');
        $row=self::signedSubscriber($id,$token,$purpose); if (!$row) throw new \InvalidArgumentException('El enlace no es válido o ha vencido.');
        $db=Database::getInstance();
        if ($purpose==='confirm') $db->execute("UPDATE goldensite_subscribers SET status='active',consented_at=NOW() WHERE id=? AND status='pending' AND consent_key=?",[$id,$row['consent_key']]);
        else $db->execute("UPDATE goldensite_subscribers SET status='inactive',consented_at=NULL WHERE id=? AND consent_key=?",[$id,$row['consent_key']]);
    }
    private static function body(array $data): string
    {
        $value=$data['body']??'';
        if (!is_string($value)||strlen($value)>50000||!mb_check_encoding($value,'UTF-8')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)) throw new \InvalidArgumentException('Contenido inválido. Máximo 50000 bytes de texto.');
        return trim($value);
    }
    public static function create(array $data): int
    {
        $subject=ConsoleView::field($data,'subject',200); $body=self::body($data);
        if ($subject===''||preg_match('/[\r\n]/',$subject)||$body==='') throw new \InvalidArgumentException('Escribe asunto y contenido. El asunto debe ocupar una sola línea.');
        $pdo=Database::getInstance()->getConnection(); $pdo->prepare('INSERT INTO goldensite_newsletter_campaigns (subject,body) VALUES (?,?)')->execute([$subject,$body]);
        return (int)$pdo->lastInsertId();
    }
    public static function save(int $id,array $data): int
    {
        if ($id<1) return self::create($data);
        $subject=ConsoleView::field($data,'subject',200); $body=self::body($data);
        if ($subject===''||preg_match('/[\r\n]/',$subject)||$body==='') throw new \InvalidArgumentException('Escribe asunto y contenido válidos.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $row=$db->fetch('SELECT status FROM goldensite_newsletter_campaigns WHERE id=? FOR UPDATE',[$id]);
            if (!$row||$row['status']!=='draft') throw new \InvalidArgumentException('Solo se edita una campaña en borrador.');
            $db->execute('UPDATE goldensite_newsletter_campaigns SET subject=?,body=? WHERE id=?',[$subject,$body,$id]); $pdo->commit(); return $id;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function queue(int $id,int $when=0): int
    {
        if (!FrontendEngine::isModuleActive('newsletter')) throw new \InvalidArgumentException('Activa el módulo y su licencia.');
        if (Settings::get('smtp_host')===''||!filter_var(Settings::get('admin_email'),FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Configura primero SMTP y el correo remitente del sitio.');
        if ($when!==0&&($when<time()-60||$when>time()+31536000)) throw new \InvalidArgumentException('La programación debe estar entre ahora y los próximos doce meses.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $campaign=$db->fetch('SELECT * FROM goldensite_newsletter_campaigns WHERE id=? FOR UPDATE',[$id]);
            if (!$campaign||$campaign['status']!=='draft') throw new \InvalidArgumentException('Solo se programa una campaña en borrador.');
            $audience=$db->fetchAll("SELECT id,consent_key FROM goldensite_subscribers WHERE status='active' AND consented_at IS NOT NULL AND consent_key IS NOT NULL ORDER BY id LIMIT 2001");
            if (!$audience||count($audience)>2000) throw new \InvalidArgumentException('La campaña admite de 1 a 2000 suscriptores con confirmación vigente.');
            foreach ($audience as $subscriber) ModuleJobs::enqueue('newsletter','newsletter','campaign:'.$id.':'.$subscriber['id'],['campaign_id'=>$id,'subscriber_id'=>(int)$subscriber['id'],'consent_key'=>$subscriber['consent_key']],$when,'campaign:'.$id);
            $db->execute("UPDATE goldensite_newsletter_campaigns SET status='queued',send_at=?,queued_at=NOW() WHERE id=?",[$when?:time(),$id]);
            $pdo->commit(); return count($audience);
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function cancel(int $id): void
    {
        $db=Database::getInstance(); $db->execute("UPDATE goldensite_newsletter_campaigns SET status='cancelled' WHERE id=?",[$id]);
        $db->execute("UPDATE goldensite_module_jobs SET state='cancelled',finished_at=NOW() WHERE group_key=? AND state IN ('queued','retry')",['campaign:'.$id]);
    }
    public static function deliver(string $kind,array $data,?callable $transport=null): ?bool
    {
        if ($transport!==null&&PHP_SAPI!=='cli') throw new \LogicException('Transporte reservado a CLI.');
        $db=Database::getInstance(); $subscriber=$db->fetch('SELECT * FROM goldensite_subscribers WHERE id=?',[(int)($data['subscriber_id']??0)]);
        if (!$subscriber||!hash_equals((string)$subscriber['consent_key'],(string)($data['consent_key']??''))) return null;
        $base=rtrim(Environment::get('APP_URL',''),'/'); if (!filter_var($base,FILTER_VALIDATE_URL)) return false;
        if ($kind==='newsletter_confirm') {
            if ($subscriber['status']!=='pending'||(int)$subscriber['optin_requested_at']<time()-172800) return null;
            $subject='Confirma tu suscripción · '.Settings::get('site_title','GoldenSiteCMS');
            $body="Solicitaste recibir nuestro boletín. Confirma tu dirección mediante este enlace en las próximas 48 horas:\n\n".$base.'/newsletter/confirm?subscriber='.$subscriber['id'].'&token='.self::token($subscriber,'confirm')."\n\nSi no hiciste esta solicitud, ignora este mensaje.";
        } else {
            $campaign=$db->fetch('SELECT * FROM goldensite_newsletter_campaigns WHERE id=?',[(int)($data['campaign_id']??0)]);
            if (!$campaign||$campaign['status']!=='queued'||$subscriber['status']!=='active'||empty($subscriber['consented_at'])) return null;
            $subject=$campaign['subject']; $body=$campaign['body']."\n\n—\n".Settings::get('site_title','GoldenSiteCMS')."\nCancelar suscripción: ".$base.'/newsletter/unsubscribe?subscriber='.$subscriber['id'].'&token='.self::token($subscriber,'unsubscribe');
        }
        return $transport?(bool)$transport('email',['to'=>$subscriber['email'],'subject'=>$subject,'body'=>$body]):MailTransport::send($subscriber['email'],$subject,$body);
    }
}
