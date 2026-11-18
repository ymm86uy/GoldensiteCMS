<?php
namespace App\Core;

/** Private queue shared by newsletter and automation; no side effects during page requests. */
final class ModuleJobs
{
    public static function enqueue(string $module,string $kind,string $key,array $payload,int $runAt=0,string $group=''): string
    {
        if (!in_array($kind,['automation','newsletter','newsletter_confirm','dating_confirm','password_reset','forum_notify','forum_mail'],true)) throw new \InvalidArgumentException('Tipo de tarea inválido.');
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (strlen($json)>65536) throw new \InvalidArgumentException('La tarea supera el tamaño permitido.');
        $id=bin2hex(random_bytes(16)); $dedupe=hash('sha256',$kind.':'.$key); $db=Database::getInstance();
        $db->execute("INSERT INTO goldensite_module_jobs (id,module_id,kind,dedupe_key,group_key,payload,state,run_at) VALUES (?,?,?,?,?,?,'queued',?) ON DUPLICATE KEY UPDATE dedupe_key=VALUES(dedupe_key)",[$id,$module,$kind,$dedupe,$group,SecretStore::seal($json),$runAt?:time()]);
        return $db->fetch('SELECT id FROM goldensite_module_jobs WHERE dedupe_key=?',[$dedupe])['id'];
    }
    public static function run(string $id,?callable $transport=null): string
    {
        if ($transport!==null&&PHP_SAPI!=='cli') throw new \LogicException('El transporte de pruebas solo se admite en CLI.');
        $db=Database::getInstance(); $row=$db->fetch('SELECT * FROM goldensite_module_jobs WHERE id=?',[$id]);
        if (!$row) throw new \InvalidArgumentException('Tarea inexistente.');
        if (($row['kind']!=='password_reset'&&!FrontendEngine::isModuleActive($row['module_id']))||($transport===null&&in_array($row['kind'],['newsletter','newsletter_confirm','dating_confirm','forum_mail'],true)&&Settings::get('smtp_host')==='')) {
            $db->execute("UPDATE goldensite_module_jobs SET run_at=UNIX_TIMESTAMP()+60 WHERE id=? AND state IN ('queued','retry')",[$id]);
            return 'paused';
        }
        $lease=bin2hex(random_bytes(16));
        $db->execute("UPDATE goldensite_module_jobs SET state='processing',attempts=attempts+1,lease_token=?,claimed_at=UNIX_TIMESTAMP() WHERE id=? AND state IN ('queued','retry') AND run_at<=UNIX_TIMESTAMP() AND attempts<5",[$lease,$id]);
        if ((int)$db->getConnection()->query('SELECT ROW_COUNT()')->fetchColumn()!==1) return $row['state'];
        try {
            $payload=json_decode(SecretStore::open($row['payload']),true,32,JSON_THROW_ON_ERROR);
            $result=match($row['kind']) {'automation'=>AutomationEngine::executeQueued($payload,$transport),'dating_confirm'=>DatingService::deliverConfirmation($payload,$transport),'password_reset'=>PasswordRecovery::deliver($payload,$transport),'forum_notify'=>\Modules\GoldensiteForum\Notifications::deliver($payload,$transport),'forum_mail'=>\Modules\GoldensiteForum\Notifications::mail($payload,$transport),default=>NewsletterService::deliver($row['kind'],$payload,$transport)};
            $state=$result===null?'cancelled':($result?'done':((int)$row['attempts']<4?'retry':'failed'));
        } catch (\Throwable $e) { $state=(int)$row['attempts']<4?'retry':'failed'; }
        $delay=min(3600,60*2**(int)$row['attempts']);
        $db->execute('UPDATE goldensite_module_jobs SET state=?,run_at=UNIX_TIMESTAMP()+?,finished_at=IF(? IN (\'done\',\'cancelled\',\'failed\'),NOW(),NULL),lease_token=NULL WHERE id=? AND lease_token=?',[$state,$delay,$state,$id,$lease]);
        return $state;
    }
    public static function work(int $limit=20,?callable $transport=null): int
    {
        $db=Database::getInstance();
        // An interrupted side effect may already have reached its receiver. Never blindly resend it.
        $db->execute("UPDATE goldensite_module_jobs SET state='uncertain',lease_token=NULL WHERE state='processing' AND claimed_at<UNIX_TIMESTAMP()-600");
        $rows=$db->fetchAll("SELECT id FROM goldensite_module_jobs WHERE state IN ('queued','retry') AND run_at<=UNIX_TIMESTAMP() AND attempts<5 ORDER BY run_at,id LIMIT ".max(1,min(100,$limit)));
        foreach ($rows as $row) self::run($row['id'],$transport);
        return count($rows);
    }
    public static function counts(string $group): array
    {
        $counts=array_fill_keys(['queued','processing','retry','done','failed','cancelled','uncertain'],0);
        foreach (Database::getInstance()->fetchAll('SELECT state,COUNT(*) n FROM goldensite_module_jobs WHERE group_key=? GROUP BY state',[$group]) as $row) $counts[$row['state']]=(int)$row['n'];
        return $counts;
    }
}
