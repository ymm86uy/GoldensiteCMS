<?php
namespace App\Core;

final class DatingService
{
    public const GENDERS=['woman'=>'Mujer','man'=>'Hombre','nonbinary'=>'No binario','other'=>'Otra identidad'];
    public const INTENTS=['relationship'=>'Relación estable','dates'=>'Conocer personas','friendship'=>'Amistad','exploring'=>'Descubrir qué busco'];
    public const REPORTS=['harassment'=>'Acoso','fake'=>'Suplantación','sexual'=>'Contenido inapropiado','minor'=>'Posible menor de edad','scam'=>'Estafa','other'=>'Otro motivo'];
    private static function text(array $data,string $key,int $max,bool $multiline=false): string
    {
        $v=$data[$key]??'';
        if (!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f]/':'/[\x00-\x1f]/',$v)) throw new \InvalidArgumentException('Revisa el campo '.$key.'.');
        return trim($v);
    }
    private static function integer(array $data,string $key,int $min,int $max): int
    {
        $v=filter_var($data[$key]??null,FILTER_VALIDATE_INT);
        if ($v===false||$v===null||$v<$min||$v>$max) throw new \InvalidArgumentException('Revisa el campo '.$key.'.'); return $v;
    }
    private static function join(): string
    {
        return ' FROM goldensite_dating_profiles p JOIN goldensite_dating_accounts a ON a.profile_id=p.id JOIN users u ON u.id=a.user_id ';
    }
    private static function selection(): string
    {
        return 'p.*,(p.last_seen>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)) presence_recent,(p.vip_expires_at IS NULL OR p.vip_expires_at>NOW()) vip_current,a.user_id account_user_id,a.birth_date actual_birth_date,TIMESTAMPDIFF(YEAR,a.birth_date,UTC_DATE()) actual_age,a.status account_status,a.review_state,a.email_verified_at,a.verified_email_hash,a.verify_nonce,a.verify_requested_at,a.min_age,a.max_age,a.hide_location,a.show_online,a.avatar_photo_id,a.relationship_intent,a.moderation_hold,u.email account_email,u.status user_status';
    }
    public static function mine(): ?array
    {
        if (!Auth::check()) return null;
        return Database::getInstance()->fetch('SELECT '.self::selection().self::join().' WHERE a.user_id=?',[(int)Auth::id()]);
    }
    public static function member(int $id): ?array { return Database::getInstance()->fetch('SELECT '.self::selection().self::join().' WHERE p.id=?',[$id]); }
    public static function verified(array $p): bool { return !empty($p['email_verified_at'])&&hash_equals((string)$p['verified_email_hash'],hash('sha256',strtolower($p['account_email']))); }
    public static function active(array $p,bool $discovery=false): bool
    {
        return in_array($p['account_status'],$discovery?['active']:['active','paused'],true)&&strtolower($p['user_status'])==='active'&&(int)$p['actual_age']>=18&&(int)$p['actual_age']<=100&&self::verified($p)&&(!$discovery||($p['review_state']==='approved'&&!empty($p['avatar_photo_id'])));
    }
    public static function publicProfile(array $p): array
    {
        return ['id'=>(int)$p['id'],'name'=>$p['username'],'age'=>(int)$p['actual_age'],'bio'=>$p['bio']??'','interests'=>self::tags($p['interests']??''),'city'=>$p['hide_location']?'':($p['location_city']??''),'country'=>$p['hide_location']?'':($p['country_code']??''),'intent'=>self::INTENTS[$p['relationship_intent']]??'','photo'=>$p['avatar_photo_id']?'/dating/media/'.$p['avatar_photo_id']:null,'online'=>(bool)$p['show_online']&&(bool)$p['presence_recent'],'vip'=>(bool)$p['is_vip']&&(bool)$p['vip_current']];
    }
    private static function tags(string $value): array
    {
        $tags=[]; foreach (explode(',',$value) as $tag) { $tag=trim($tag); if ($tag!==''&&mb_strlen($tag)<=24) $tags[mb_strtolower($tag)]=$tag; } return array_slice(array_values($tags),0,10);
    }
    private static function profileInput(array $data): array
    {
        $name=self::text($data,'display_name',70); $bio=self::text($data,'bio',1500,true); $gender=self::text($data,'gender',16); $seeking=self::text($data,'seeking',16); $intent=self::text($data,'intent',20);
        if (mb_strlen($name)<2||mb_strlen($bio)<30||!isset(self::GENDERS[$gender])||($seeking!=='all'&&!isset(self::GENDERS[$seeking]))||!isset(self::INTENTS[$intent])) throw new \InvalidArgumentException('Completa nombre, presentación de al menos 30 caracteres y preferencias válidas.');
        $min=self::integer($data,'min_age',18,100); $max=self::integer($data,'max_age',18,100); if ($max<$min) throw new \InvalidArgumentException('El rango de edad está invertido.');
        $country=strtoupper(self::text($data,'country',2)); if ($country!==''&&!preg_match('/^[A-Z]{2}$/D',$country)) throw new \InvalidArgumentException('Usa un código de país de dos letras.');
        return ['name'=>$name,'bio'=>$bio,'gender'=>$gender,'seeking'=>$seeking,'intent'=>$intent,'min'=>$min,'max'=>$max,'city'=>self::text($data,'city',100),'country'=>$country,'interests'=>implode(', ',self::tags(self::text($data,'interests',300))),'hide_location'=>isset($data['hide_location'])?1:0,'show_online'=>isset($data['show_online'])?1:0];
    }
    public static function onboard(array $data): void
    {
        $v=self::profileInput($data); $birth=self::text($data,'birth_date',10); $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$birth,new \DateTimeZone('UTC')); $today=new \DateTimeImmutable('today',new \DateTimeZone('UTC'));
        if (!$date||$date->format('Y-m-d')!==$birth||$date>$today||($age=$date->diff($today)->y)<18||$age>100||($data['adult_consent']??'')!=='1'||($data['community_consent']??'')!=='1') throw new \InvalidArgumentException('La comunidad es para mayores de 18 años. Revisa la fecha y acepta sus normas.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $user=$db->fetch('SELECT id,username,email FROM users WHERE id=? FOR UPDATE',[(int)Auth::id()]); $existing=self::mine();
            if ($existing&&($existing['account_status']!=='deleted'||(int)$existing['moderation_hold'])) throw new \InvalidArgumentException('Tu perfil ya existe o necesita revisión de administración.');
            $profile=$existing?:$db->fetch('SELECT * FROM goldensite_dating_profiles WHERE user_id=? AND id NOT IN (SELECT profile_id FROM goldensite_dating_accounts) ORDER BY id LIMIT 1',[$user['id']]);
            if (!$profile) { $db->execute("INSERT INTO goldensite_dating_profiles (user_id,username,email,birth_date,age,wallet_balance,avatar_approved,status) VALUES (?,?,?,?,?,0,0,'ACTIVE')",[$user['id'],$v['name'],$user['email'],$birth,$age]); $id=(int)$pdo->lastInsertId(); }
            else $id=(int)$profile['id'];
            $db->execute("UPDATE goldensite_dating_profiles SET username=?,email=?,birth_date=?,age=?,gender=?,looking_for=?,bio=?,interests=?,location_city=?,country_code=?,status='ACTIVE' WHERE id=?",[$v['name'],$user['email'],$birth,$age,$v['gender'],$v['seeking'],$v['bio'],$v['interests'],$v['city'],$v['country'],$id]);
            $nonce=bin2hex(random_bytes(16));
            $db->execute("INSERT INTO goldensite_dating_accounts (user_id,profile_id,birth_date,verify_nonce,verify_requested_at,min_age,max_age,hide_location,show_online,relationship_intent) VALUES (?,?,?,?,UNIX_TIMESTAMP(),?,?,?,?,?) ON DUPLICATE KEY UPDATE birth_date=VALUES(birth_date),status='active',review_state='pending',email_verified_at=NULL,verified_email_hash=NULL,verify_nonce=VALUES(verify_nonce),verify_requested_at=VALUES(verify_requested_at),min_age=VALUES(min_age),max_age=VALUES(max_age),hide_location=VALUES(hide_location),show_online=VALUES(show_online),relationship_intent=VALUES(relationship_intent),consent_at=NOW(),avatar_photo_id=NULL",[$user['id'],$id,$birth,$nonce,$v['min'],$v['max'],$v['hide_location'],$v['show_online'],$v['intent']]);
            ModuleJobs::enqueue('dating','dating_confirm','dating-confirm:'.$user['id'].':'.$nonce,['user_id'=>(int)$user['id'],'nonce'=>$nonce,'email_hash'=>hash('sha256',strtolower($user['email']))]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function update(array $data): void
    {
        $p=self::mine(); if (!$p||$p['account_status']==='deleted') throw new \InvalidArgumentException('Crea tu perfil primero.'); $v=self::profileInput($data); $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->execute('UPDATE goldensite_dating_profiles SET username=?,gender=?,looking_for=?,bio=?,interests=?,location_city=?,country_code=?,last_seen=NOW() WHERE id=?',[$v['name'],$v['gender'],$v['seeking'],$v['bio'],$v['interests'],$v['city'],$v['country'],$p['id']]);
            $db->execute("UPDATE goldensite_dating_accounts SET min_age=?,max_age=?,hide_location=?,show_online=?,relationship_intent=?,review_state='pending',updated_at=NOW() WHERE user_id=?",[$v['min'],$v['max'],$v['hide_location'],$v['show_online'],$v['intent'],Auth::id()]); $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function requestVerification(): void
    {
        $p=self::mine(); if (!$p||!in_array($p['account_status'],['active','paused'],true)) throw new \InvalidArgumentException('El perfil no admite confirmación.');
        if (!RateLimiter::consume('dating-confirm:'.Auth::id(),3,3600)) throw new \InvalidArgumentException('Espera antes de solicitar otro correo.');
        $nonce=bin2hex(random_bytes(16)); Database::getInstance()->execute('UPDATE goldensite_dating_accounts SET verify_nonce=?,verify_requested_at=UNIX_TIMESTAMP() WHERE user_id=?',[$nonce,Auth::id()]);
        ModuleJobs::enqueue('dating','dating_confirm','dating-confirm:'.Auth::id().':'.$nonce,['user_id'=>(int)Auth::id(),'nonce'=>$nonce,'email_hash'=>hash('sha256',strtolower($p['account_email']))]);
    }
    public static function verificationToken(array $p): string { return hash_hmac('sha256','dating-confirm:'.$p['account_user_id'].':'.$p['verify_nonce'].':'.hash('sha256',strtolower($p['account_email'])),KeyStore::vault()); }
    public static function confirmation(int $userId,string $token,bool $apply=false): bool
    {
        $p=Database::getInstance()->fetch('SELECT '.self::selection().self::join().' WHERE a.user_id=?',[$userId]);
        if (!$p||!in_array($p['account_status'],['active','paused'],true)||(int)$p['verify_requested_at']<time()-172800||!preg_match('/^[a-f0-9]{64}$/D',$token)||!hash_equals(self::verificationToken($p),$token)) return false;
        if ($apply) Database::getInstance()->execute('UPDATE goldensite_dating_accounts SET email_verified_at=NOW(),verified_email_hash=? WHERE user_id=? AND verify_nonce=?',[hash('sha256',strtolower($p['account_email'])),$userId,$p['verify_nonce']]);
        return true;
    }
    public static function deliverConfirmation(array $data,?callable $transport=null): ?bool
    {
        if ($transport&&PHP_SAPI!=='cli') throw new \LogicException('Transporte reservado a CLI.');
        $p=Database::getInstance()->fetch('SELECT '.self::selection().self::join().' WHERE a.user_id=?',[(int)($data['user_id']??0)]);
        if (!$p||!in_array($p['account_status'],['active','paused'],true)||self::verified($p)||(int)$p['verify_requested_at']<time()-172800||!hash_equals($p['verify_nonce'],(string)($data['nonce']??''))||!hash_equals(hash('sha256',strtolower($p['account_email'])),(string)($data['email_hash']??''))) return null;
        $url=rtrim(Environment::get('APP_URL'),'/').'/dating/confirm?user='.$p['account_user_id'].'&token='.self::verificationToken($p);
        $mail=['to'=>$p['account_email'],'subject'=>'Confirma tu correo · Goldensite Love Dating','body'=>"Solicitaste crear un perfil en Goldensite Love Dating. Confirma tu dirección durante las próximas 48 horas:\n\n".$url."\n\nSi no hiciste esta solicitud, ignora este mensaje."];
        return $transport?(bool)$transport('email',$mail):MailTransport::send($mail['to'],$mail['subject'],$mail['body']);
    }
    public static function blocked(int $one,int $other): bool
    {
        return (bool)Database::getInstance()->fetch('SELECT 1 found FROM goldensite_dating_privacy_blocks WHERE (blocker_id=? AND target_id=?) OR (blocker_id=? AND target_id=?) LIMIT 1',[$one,$other,$other,$one]);
    }
    public static function discover(array $filters): array
    {
        $me=self::mine(); if (!$me||!self::active($me,true)) return ['profiles'=>[],'total'=>0,'page'=>1,'pages'=>0];
        $min=max((int)$me['min_age'],isset($filters['min_age'])?self::integer($filters,'min_age',18,100):18); $max=min((int)$me['max_age'],isset($filters['max_age'])?self::integer($filters,'max_age',18,100):100); if ($min>$max) throw new \InvalidArgumentException('El rango solicitado no coincide con tus preferencias.');
        $page=isset($filters['page'])?self::integer($filters,'page',1,10000):1;
        $where=" WHERE a.status='active' AND a.review_state='approved' AND a.email_verified_at IS NOT NULL AND a.verified_email_hash=SHA2(LOWER(u.email),256) AND u.status='active' AND a.avatar_photo_id IS NOT NULL AND p.id<>? AND TIMESTAMPDIFF(YEAR,a.birth_date,UTC_DATE()) BETWEEN ? AND ? AND ? BETWEEN a.min_age AND a.max_age AND (p.looking_for='all' OR p.looking_for=?) AND NOT EXISTS (SELECT 1 FROM goldensite_dating_privacy_blocks b WHERE (b.blocker_id=? AND b.target_id=p.id) OR (b.target_id=? AND b.blocker_id=p.id)) AND NOT EXISTS (SELECT 1 FROM goldensite_dating_likes l WHERE l.user_id=? AND l.target_id=p.id)";
        $params=[$me['id'],$min,$max,(int)$me['actual_age'],$me['gender'],$me['id'],$me['id'],$me['id']];
        if ($me['looking_for']!=='all') { $where.=' AND p.gender=?'; $params[]=$me['looking_for']; }
        $city=self::text($filters,'city',100); if ($city!=='') { $where.=' AND a.hide_location=0 AND p.location_city=?'; $params[]=$city; }
        $db=Database::getInstance(); $total=(int)$db->fetch('SELECT COUNT(*) n'.self::join().$where,$params)['n'];
        $profiles=$db->fetchAll('SELECT '.self::selection().self::join().$where.' ORDER BY p.id DESC LIMIT 12 OFFSET '.(($page-1)*12),$params); $items=[];
        foreach ($profiles as $p) { $dto=self::publicProfile($p); $dto['shared']=array_values(array_intersect(array_map('mb_strtolower',self::tags($me['interests']??'')),array_map('mb_strtolower',$dto['interests']))); $items[]=$dto; }
        return ['profiles'=>$items,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/12)];
    }
    public static function like(int $target,bool $skip=false): bool
    {
        $me=self::mine(); $peer=self::member($target);
        if (!$me||!$peer||$target===(int)$me['id']||!self::active($me,true)||!self::active($peer,true)||self::blocked($me['id'],$target)) throw new \InvalidArgumentException('El perfil no está disponible para esta interacción.');
        if (($me['looking_for']!=='all'&&$me['looking_for']!==$peer['gender'])||($peer['looking_for']!=='all'&&$peer['looking_for']!==$me['gender'])||$peer['actual_age']<$me['min_age']||$peer['actual_age']>$me['max_age']||$me['actual_age']<$peer['min_age']||$me['actual_age']>$peer['max_age']) throw new \InvalidArgumentException('El perfil no coincide con las preferencias mutuas.');
        if (!RateLimiter::consume('dating-like:'.Auth::id(),30,300)||!RateLimiter::consume('dating-like-day:'.Auth::id(),200,86400)) throw new \InvalidArgumentException('Has alcanzado el límite de interacciones. Inténtalo más tarde.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $low=min((int)$me['id'],$target); $high=max((int)$me['id'],$target);
            // Serialize reciprocal likes and block/unmatch changes on the same pair.
            $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$low]); $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$high]);
            $actor=self::mine(); $receiver=self::member($target);
            if (!$actor||!$receiver||!self::active($actor,true)||!self::active($receiver,true)||self::blocked($low,$high)) throw new \InvalidArgumentException('Esta interacción ya no está disponible.');
            $db->execute('INSERT INTO goldensite_dating_likes (user_id,target_id,type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE type=VALUES(type)',[$me['id'],$target,$skip?'pass':'like']);
            $mutual=!$skip&&(bool)$db->fetch("SELECT id FROM goldensite_dating_likes WHERE user_id=? AND target_id=? AND type='like'",[$target,$me['id']]);
            if ($mutual) $db->execute("INSERT INTO goldensite_dating_relationships (low_profile,high_profile,status) VALUES (?,?,'active') ON DUPLICATE KEY UPDATE status='active'",[$low,$high]);
            $pdo->commit(); return $mutual;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function match(int $id): ?array
    {
        $me=self::mine(); if (!$me||!self::active($me)) return null;
        $match=Database::getInstance()->fetch("SELECT * FROM goldensite_dating_relationships WHERE id=? AND status='active' AND (low_profile=? OR high_profile=?)",[$id,$me['id'],$me['id']]);
        if (!$match) return null; $target=(int)$match['low_profile']===(int)$me['id']?(int)$match['high_profile']:(int)$match['low_profile']; $peer=self::member($target);
        if (!$peer||!self::active($peer)||self::blocked($me['id'],$target)) return null;
        return $match+['peer'=>self::publicProfile($peer),'mine'=>(int)$me['id']];
    }
    public static function matches(): array
    {
        $me=self::mine(); if (!$me) return []; $rows=Database::getInstance()->fetchAll("SELECT id FROM goldensite_dating_relationships WHERE status='active' AND (low_profile=? OR high_profile=?) ORDER BY id DESC LIMIT 100",[$me['id'],$me['id']]); $out=[];
        foreach ($rows as $row) if ($match=self::match((int)$row['id'])) $out[]=$match; return $out;
    }
    public static function messages(int $id,int $after=0): array
    {
        $match=self::match($id); if (!$match) throw new \InvalidArgumentException('Conversación no disponible.'); $db=Database::getInstance();
        $rows=$db->fetchAll('SELECT id,sender_id,message,media_url,media_type,gift_icon,is_read,created_at FROM goldensite_dating_messages WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id>? ORDER BY id DESC LIMIT 100',[$match['mine'],$match['peer']['id'],$match['peer']['id'],$match['mine'],$after]);
        $out=[]; foreach (array_reverse($rows) as $row) { $row['message']=SecretStore::open($row['message']??''); $row['mine']=(int)$row['sender_id']===$match['mine']; unset($row['sender_id']); if (!preg_match('~^/dating/media/[a-f0-9]{32}$~D',$row['media_url']??'')) $row['media_url']=null; $out[]=$row; }
        $db->execute('UPDATE goldensite_dating_messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0',[$match['peer']['id'],$match['mine']]); return $out;
    }
    public static function send(int $id,array $data): void
    {
        if (!RateLimiter::consume('dating-message:'.Auth::id(),30,60)) throw new \InvalidArgumentException('Espera antes de enviar más mensajes.');
        self::inPair($id,static function($match) use ($data) {
            $message=self::text($data,'message',2000,true); $photo=self::text($data,'photo_id',32); $url=null;
            if ($photo!=='') { $row=Database::getInstance()->fetch("SELECT id FROM goldensite_dating_photos WHERE id=? AND profile_id=? AND status='approved'",[$photo,$match['mine']]); if (!$row) throw new \InvalidArgumentException('Selecciona una foto aprobada de tu álbum.'); $url='/dating/media/'.$photo; }
            if ($message===''&&$url===null) throw new \InvalidArgumentException('Escribe un mensaje o selecciona una foto.');
            Database::getInstance()->execute('INSERT INTO goldensite_dating_messages (sender_id,receiver_id,message,media_url,media_type) VALUES (?,?,?,?,?)',[$match['mine'],$match['peer']['id'],SecretStore::seal($message),$url,$url?'image':null]);
        });
    }
    private static function inPair(int $matchId,callable $action): void
    {
        $match=self::match($matchId); if (!$match) throw new \InvalidArgumentException('Necesitas una coincidencia mutua activa para esta acción.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$match['low_profile']]); $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$match['high_profile']]);
            $current=self::match($matchId); if (!$current) throw new \InvalidArgumentException('La coincidencia ya no está disponible.');
            $action($current); $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function relationship(int $target,string $action): void
    {
        $me=self::mine(); if (!$me||$target<1||$target===(int)$me['id']||!self::member($target)||!in_array($action,['block','unblock','unmatch'],true)) throw new \InvalidArgumentException('Interacción inválida.');
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $low=min((int)$me['id'],$target); $high=max((int)$me['id'],$target); $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$low]); $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$high]);
            if ($action==='block') $db->execute('INSERT IGNORE INTO goldensite_dating_privacy_blocks (blocker_id,target_id) VALUES (?,?)',[$me['id'],$target]);
            if ($action==='unblock') $db->execute('DELETE FROM goldensite_dating_privacy_blocks WHERE blocker_id=? AND target_id=?',[$me['id'],$target]);
            else { $db->execute("UPDATE goldensite_dating_relationships SET status='unmatched' WHERE low_profile=? AND high_profile=?",[$low,$high]); $db->execute('DELETE FROM goldensite_dating_likes WHERE (user_id=? AND target_id=?) OR (user_id=? AND target_id=?)',[$low,$high,$high,$low]); }
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function report(int $target,array $data): void
    {
        $me=self::mine(); $reason=self::text($data,'reason',24); $detail=self::text($data,'detail',2000,true);
        if (!$me||$target===(int)$me['id']||!self::member($target)||!isset(self::REPORTS[$reason])||mb_strlen($detail)<10) throw new \InvalidArgumentException('Selecciona un motivo y describe lo ocurrido.');
        if (!RateLimiter::consume('dating-report:'.Auth::id(),5,86400)) throw new \InvalidArgumentException('Has alcanzado el límite diario de denuncias. Contacta con administración.');
        $messageId=filter_var($data['message_id']??0,FILTER_VALIDATE_INT)?:0; $evidence=null;
        if ($messageId) { $message=Database::getInstance()->fetch('SELECT message FROM goldensite_dating_messages WHERE id=? AND sender_id=? AND receiver_id=?',[$messageId,$target,$me['id']]); if (!$message) throw new \InvalidArgumentException('El mensaje indicado no pertenece a esta conversación.'); $evidence=SecretStore::seal(SecretStore::open($message['message']??'')); }
        Database::getInstance()->execute("INSERT INTO goldensite_dating_safety_reports (reporter_id,target_id,reason,detail,message_id,message_evidence) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),detail=VALUES(detail),message_id=VALUES(message_id),message_evidence=VALUES(message_evidence),status='open',moderator_note=NULL,reviewed_by=NULL",[$me['id'],$target,$reason,$detail,$messageId?:null,$evidence]);
        if (isset($data['block_also'])) self::relationship($target,'block');
    }
    public static function pause(bool $pause): void
    {
        $me=self::mine(); if (!$me||!in_array($me['account_status'],['active','paused'],true)) throw new \InvalidArgumentException('Tu perfil requiere revisión de administración.');
        Database::getInstance()->execute('UPDATE goldensite_dating_accounts SET status=? WHERE user_id=?',[$pause?'paused':'active',Auth::id()]);
    }
    public static function photos(): array
    {
        $me=self::mine(); return $me?Database::getInstance()->fetchAll("SELECT id,status,created_at FROM goldensite_dating_photos WHERE profile_id=? AND status<>'deleted' ORDER BY created_at DESC",[$me['id']]):[];
    }
    public static function avatar(string $photo): void
    {
        $me=self::mine(); if (!$me||!Database::getInstance()->fetch("SELECT id FROM goldensite_dating_photos WHERE id=? AND profile_id=? AND status='approved'",[$photo,$me['id']])) throw new \InvalidArgumentException('Elige una foto aprobada de tu álbum.');
        Database::getInstance()->execute('UPDATE goldensite_dating_accounts SET avatar_photo_id=? WHERE user_id=?',[$photo,Auth::id()]);
    }
    public static function story(array $data): void
    {
        $me=self::mine(); if (!$me||!self::active($me,true)) throw new \InvalidArgumentException('Completa y confirma tu perfil antes de publicar historias.');
        if (!RateLimiter::consume('dating-story:'.Auth::id(),5,86400)) throw new \InvalidArgumentException('Has alcanzado el límite diario de historias.');
        $photo=self::text($data,'photo_id',32); $caption=self::text($data,'caption',160);
        if (!Database::getInstance()->fetch("SELECT id FROM goldensite_dating_photos WHERE id=? AND profile_id=? AND status='approved'",[$photo,$me['id']])) throw new \InvalidArgumentException('Selecciona una foto aprobada.');
        Database::getInstance()->execute("INSERT INTO goldensite_dating_stories (user_id,media_url,photo_id,caption,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))",[$me['id'],'/dating/media/'.$photo,$photo,$caption]);
    }
    public static function stories(): array
    {
        $me=self::mine(); if (!$me||!self::active($me,true)) return [];
        $rows=Database::getInstance()->fetchAll("SELECT s.id,s.caption,s.photo_id,s.expires_at,p.username,p.id profile_id FROM goldensite_dating_stories s JOIN goldensite_dating_photos ph ON ph.id=s.photo_id AND ph.status='approved' JOIN goldensite_dating_profiles p ON p.id=s.user_id JOIN goldensite_dating_accounts a ON a.profile_id=p.id JOIN users u ON u.id=a.user_id WHERE s.expires_at>NOW() AND s.is_hidden=0 AND a.status='active' AND a.review_state='approved' AND a.email_verified_at IS NOT NULL AND a.verified_email_hash=SHA2(LOWER(u.email),256) AND u.status='active' AND TIMESTAMPDIFF(YEAR,a.birth_date,UTC_DATE()) BETWEEN 18 AND 100 AND NOT EXISTS (SELECT 1 FROM goldensite_dating_privacy_blocks b WHERE (b.blocker_id=? AND b.target_id=p.id) OR (b.target_id=? AND b.blocker_id=p.id)) ORDER BY s.id DESC LIMIT 30",[$me['id'],$me['id']]);
        return $rows;
    }
    public static function removeStory(int $id): void
    {
        $me=self::mine(); if (!$me) throw new \InvalidArgumentException('Perfil no disponible.');
        Database::getInstance()->execute('UPDATE goldensite_dating_stories SET is_hidden=1 WHERE id=? AND user_id=?',[$id,$me['id']]);
    }
    public static function gift(int $matchId,int $giftId): void
    {
        if (!RateLimiter::consume('dating-gift:'.Auth::id(),10,300)) throw new \InvalidArgumentException('Espera antes de enviar más regalos.');
        self::inPair($matchId,static function($match) use ($giftId) { try { WalletService::sendGift(Database::getInstance()->getConnection(),$match['mine'],$match['peer']['id'],$giftId,''); } catch (\PDOException $e) { throw $e; } catch (\RuntimeException $e) { throw new \InvalidArgumentException($e->getMessage()); } });
    }
    public static function export(): array
    {
        $me=self::mine(); if (!$me) throw new \InvalidArgumentException('Perfil no disponible.'); $db=Database::getInstance();
        $own=$me; foreach (['verify_nonce','verified_email_hash'] as $key) unset($own[$key]);
        $messages=$db->fetchAll('SELECT id,sender_id,receiver_id,message,created_at FROM goldensite_dating_messages WHERE sender_id=? OR receiver_id=? ORDER BY id DESC LIMIT 10000',[$me['id'],$me['id']]); foreach ($messages as &$message) $message['message']=SecretStore::open($message['message']??''); unset($message);
        return ['format'=>'goldensite-dating-activity-v1','generated_at'=>gmdate('c'),'profile'=>$own,'photos'=>self::photos(),'messages'=>$messages,'message_export_limit'=>10000,'likes'=>$db->fetchAll('SELECT target_id,type,created_at FROM goldensite_dating_likes WHERE user_id=? LIMIT 10000',[$me['id']]),'blocks'=>$db->fetchAll('SELECT target_id,created_at FROM goldensite_dating_privacy_blocks WHERE blocker_id=?',[$me['id']]),'credits'=>$db->fetchAll('SELECT type,amount,description,created_at FROM goldensite_dating_transactions WHERE user_id=? ORDER BY id DESC LIMIT 10000',[$me['id']])];
    }
    public static function close(): void
    {
        $me=self::mine(); if (!$me) throw new \InvalidArgumentException('Perfil no disponible.'); $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$me['id']]);
            $db->execute("UPDATE goldensite_dating_accounts SET status='deleted',review_state='pending',birth_date=NULL,email_verified_at=NULL,verified_email_hash=NULL,avatar_photo_id=NULL,verify_nonce=?,verify_requested_at=0 WHERE user_id=?",[bin2hex(random_bytes(16)),Auth::id()]);
            $db->execute("UPDATE goldensite_dating_profiles SET username='Perfil cerrado',email='',birth_date=NULL,bio='',interests='',hobbies='',location_city='',location_country='',country_code='',avatar_url=NULL,wallet_balance=0,is_vip=0,vip_expires_at=NULL,status='DELETED' WHERE id=?",[$me['id']]);
            $db->execute("UPDATE goldensite_dating_relationships SET status='unmatched' WHERE low_profile=? OR high_profile=?",[$me['id'],$me['id']]);
            $db->execute('DELETE FROM goldensite_dating_likes WHERE user_id=? OR target_id=?',[$me['id'],$me['id']]);
            $db->execute("UPDATE goldensite_dating_messages SET message='[Mensaje retirado por cierre del perfil]',media_url=NULL WHERE sender_id=?",[$me['id']]);
            $db->execute('UPDATE goldensite_dating_stories SET is_hidden=1 WHERE user_id=?',[$me['id']]);
            $photos=$db->fetchAll("SELECT id FROM goldensite_dating_photos WHERE profile_id=? AND status<>'deleted'",[$me['id']]);
            $db->execute("UPDATE goldensite_dating_photos SET status='deleted' WHERE profile_id=?",[$me['id']]); $pdo->commit();
            foreach ($photos as $photo) { $path=DatingMedia::path($photo['id']); if (is_file($path)) @unlink($path); }
            ConsoleView::audit('dating_profile_closed','profile:'.$me['id']);
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
