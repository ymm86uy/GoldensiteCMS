<?php
declare(strict_types=1);
namespace App\Core;

final class EditorialWorkflow
{
    private const TABLES=['page'=>'goldensite_pages','post'=>'goldensite_posts'];

    public static function get(string $entity,int $id): ?array
    {
        return Database::getInstance()->fetch('SELECT * FROM goldensite_editorial_queue WHERE entity=? AND document_id=?',[$entity,$id]);
    }

    public static function remove(string $entity,int $id): void
    {
        Database::getInstance()->execute('DELETE FROM goldensite_editorial_queue WHERE entity=? AND document_id=?',[$entity,$id]);
    }

    public static function change(string $entity,int $id,string $action,string $revision,string $date): void
    {
        $scope=$entity==='page'?'pages':'posts';
        $permission=match($action){'request_review'=>'write','approve_review'=>'review','schedule_publish','cancel_schedule'=>'publish'};
        if (!IdentityAccess::allows($scope.'.'.$permission)) throw new \InvalidArgumentException('Tu cuenta no puede realizar este paso editorial.');
        $table=self::TABLES[$entity];
        $db=Database::getInstance();
        $conn=$db->getConnection();
        $conn->beginTransaction();
        try {
            $document=$db->fetch("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]);
            if (!$document || $document['status']!=='draft' || (int)$document['is_published']!==0
                || !hash_equals(EditorialHistory::fingerprint($document),$revision)) throw new \InvalidArgumentException('Abre la versión guardada del borrador para continuar.');
            $queue=self::get($entity,$id);
            $current=$queue['state']??null;
            if ($queue && !hash_equals($queue['fingerprint'],$revision)) throw new \InvalidArgumentException('El borrador cambió.');
            $user=(int)Auth::id();
            if ($action==='request_review' && ($current===null || $current==='stale')) {
                self::remove($entity,$id);
                $db->execute("INSERT INTO goldensite_editorial_queue (entity,document_id,fingerprint,state,requested_by) VALUES (?,?,?,'review',?)",[$entity,$id,$revision,$user]);
            } elseif ($action==='approve_review' && $current==='review') {
                if (!IdentityAccess::superuser() && (int)$queue['requested_by']===$user) throw new \InvalidArgumentException('Otra cuenta revisora debe aprobar este borrador.');
                $db->execute("UPDATE goldensite_editorial_queue SET state='approved',reviewed_by=? WHERE entity=? AND document_id=?",[$user,$entity,$id]);
            } elseif ($action==='schedule_publish' && $current==='approved') {
                $timezone=new \DateTimeZone('UTC');
                $scheduled=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,$timezone);
                if (!$scheduled || $scheduled->format('Y-m-d\TH:i')!==$date || $scheduled->getTimestamp()<=time()) throw new \InvalidArgumentException('Elige una fecha y hora UTC posteriores a este momento.');
                $db->execute("UPDATE goldensite_editorial_queue SET state='scheduled',publish_at=?,scheduled_by=? WHERE entity=? AND document_id=?",[$scheduled->getTimestamp(),$user,$entity,$id]);
            } elseif ($action==='cancel_schedule' && $current==='scheduled') {
                $db->execute("UPDATE goldensite_editorial_queue SET state='approved',publish_at=NULL,scheduled_by=NULL WHERE entity=? AND document_id=?",[$entity,$id]);
            } else throw new \InvalidArgumentException('El borrador no está en el estado requerido para esta acción.');
            $conn->commit();
        } finally { if ($conn->inTransaction()) $conn->rollBack(); }
    }

    public static function publishDue(): int
    {
        $db=Database::getInstance();
        $published=0;
        foreach ($db->fetchAll("SELECT entity,document_id FROM goldensite_editorial_queue WHERE state='scheduled' AND publish_at<=? ORDER BY publish_at LIMIT 50",[time()]) as $task) {
            $entity=$task['entity']; $id=(int)$task['document_id']; $table=self::TABLES[$entity];
            $conn=$db->getConnection();
            $conn->beginTransaction();
            try {
                $document=$db->fetch("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]);
                $queue=self::get($entity,$id);
                if (!$queue || $queue['state']!=='scheduled' || (int)$queue['publish_at']>time()) continue;
                if (!$document || $document['status']!=='draft' || (int)$document['is_published']!==0
                    || !hash_equals($queue['fingerprint'],EditorialHistory::fingerprint($document))) {
                    $db->execute("UPDATE goldensite_editorial_queue SET state='stale' WHERE entity=? AND document_id=?",[$entity,$id]);
                    $conn->commit();
                    continue;
                }
                $author=$db->fetch('SELECT id,username FROM users WHERE id=?',[$queue['scheduled_by']]);
                EditorialHistory::record($entity,$document,null);
                $db->execute("UPDATE $table SET status='published',is_published=1 WHERE id=?",[$id]);
                EditorialHistory::record($entity,array_replace($document,['status'=>'published']),$author);
                $event=$entity.'_published';
                WebhookEngine::enqueue($event,($entity==='page'?'Página publicada: ':'Artículo publicado: ').$document['title']);
                self::remove($entity,$id);
                $conn->commit();
                $published++;
                CacheStore::clear();
                AutomationEngine::trigger($event,['title'=>$document['title'],'id'=>$id,'status'=>'published']);
            } finally { if ($conn->inTransaction()) $conn->rollBack(); }
        }
        return $published;
    }
}
