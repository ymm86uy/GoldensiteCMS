<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum;

use App\Core\{Environment, MailTransport, ModuleJobs};

final class Notifications
{
    public static function record(int $topic, ?int $reply, int $actor, ?int $author, string $body): void
    {
        $db = Forum::db();
        $audience = [];
        if ($reply && $author) $audience[$author] = 'reply';
        // Fan-out stays in the database, independent of the number of subscribers.
        if ($reply) $db->execute("INSERT INTO goldensite_forum_notifications (user_id,topic_id,reply_id,reason)
            SELECT f.user_id, f.topic_id, ?, 'follow' FROM goldensite_forum_follows f JOIN users u ON u.id=f.user_id
            WHERE f.topic_id=? AND f.user_id<>? AND u.status IN ('active','enabled','1')", [$reply,$topic,$actor]);
        $body = preg_replace('/^```[^\r\n]*\R[\s\S]*?^```[^\r\n]*|(?m)^```[^\r\n]*\R[\s\S]*\z/m', '', $body);
        preg_match_all('/(?<![\p{L}\p{N}_.@])@([\p{L}\p{N}_.-]{1,100})/u', $body, $matches);
        foreach (array_slice(array_unique($matches[1]),0,10) as $name) {
            $user = $db->fetch("SELECT id FROM users WHERE username=? AND status IN ('active','enabled','1')", [rtrim($name,'.')]);
            if ($user) $audience[(int)$user['id']] = 'mention';
        }
        unset($audience[$actor]);
        foreach ($audience as $id=>$reason) {
            if ($reply) $db->execute('DELETE FROM goldensite_forum_notifications WHERE user_id=? AND reply_id=?', [$id,$reply]);
            $db->execute("INSERT INTO goldensite_forum_notifications (user_id,topic_id,reply_id,reason)
                SELECT id,?,?,? FROM users WHERE id=? AND status IN ('active','enabled','1')", [$topic,$reply,$reason,$id]);
        }
        foreach ($audience as $id=>$reason) {
            if ($db->fetch('SELECT user_id FROM goldensite_forum_preferences WHERE user_id=? AND direct_email=1',[$id])) {
                ModuleJobs::enqueue('forum','forum_mail',$topic.':'.($reply??0).':'.$id,['topic'=>$topic,'reply'=>$reply,'user'=>$id,'direct'=>true]);
            }
        }
        if ($reply && $db->fetch('SELECT user_id FROM goldensite_forum_follows WHERE topic_id=? AND email_enabled=1 AND user_id<>? LIMIT 1', [$topic,$actor])) {
            ModuleJobs::enqueue('forum','forum_notify',$topic.':'.($reply??0),['topic'=>$topic,'reply'=>$reply,'actor'=>$actor,'after'=>0]);
        }
    }

    public static function deliver(array $payload, ?callable $transport = null): ?bool
    {
        $db = Forum::db();
        $topicId = (int)$payload['topic'];
        $replyId = $payload['reply'] ? (int)$payload['reply'] : null;
        $base = rtrim(Environment::get('APP_URL'), '/');
        if (!filter_var($base,FILTER_VALIDATE_URL) || !str_starts_with($base,'https://')) return null;
        $rows = $db->fetchAll("SELECT u.id,u.email,t.slug FROM goldensite_forum_follows f
            JOIN users u ON u.id=f.user_id JOIN goldensite_forum_topics t ON t.id=f.topic_id
            WHERE f.topic_id=? AND f.email_enabled=1 AND f.user_id>? AND f.user_id<>?
            AND t.deleted_at IS NULL AND u.status IN ('active','enabled','1')
            AND (? IS NULL OR EXISTS (SELECT 1 FROM goldensite_forum_replies r WHERE r.topic_id=t.id AND r.id=? AND r.deleted_at IS NULL))
            ORDER BY f.user_id LIMIT 26", [$topicId,(int)$payload['after'],(int)$payload['actor'],$replyId,$replyId]);
        if (!$rows) return null;
        // Each recipient is its own delivery job, so retries cannot resend to the entire batch.
        foreach (array_slice($rows,0,25) as $row) {
            ModuleJobs::enqueue('forum','forum_mail',$topicId.':'.($replyId??0).':'.$row['id'],
                ['topic'=>$topicId,'reply'=>$replyId,'user'=>(int)$row['id']]);
        }
        if (count($rows)>25) {
            $payload['after'] = (int)$rows[24]['id'];
            ModuleJobs::enqueue('forum','forum_notify',$topicId.':'.($replyId??0).':'.$payload['after'],$payload);
        }
        return true;
    }

    public static function mail(array $payload, ?callable $transport = null): ?bool
    {
        $preference = '(EXISTS (SELECT 1 FROM goldensite_forum_follows f WHERE f.user_id=u.id AND f.topic_id=t.id AND f.email_enabled=1)
            OR (?=1 AND EXISTS (SELECT 1 FROM goldensite_forum_preferences p WHERE p.user_id=u.id AND p.direct_email=1)))';
        $row = Forum::db()->fetch("SELECT u.email,t.slug,t.id FROM users u
            JOIN goldensite_forum_topics t ON t.id=?
            WHERE u.id=? AND $preference AND t.deleted_at IS NULL AND u.status IN ('active','enabled','1')
            AND (? IS NULL OR EXISTS (SELECT 1 FROM goldensite_forum_replies r WHERE r.topic_id=t.id AND r.id=? AND r.deleted_at IS NULL))",
            [$payload['topic'],$payload['user'],(int)!empty($payload['direct']),$payload['reply'],$payload['reply']]);
        $base = rtrim(Environment::get('APP_URL'),'/');
        if (!$row || !filter_var($base,FILTER_VALIDATE_URL) || !str_starts_with($base,'https://')) return null;
        $url = $base.\Modules\GoldensiteForum\Models\TopicModel::url($row);
        $message = ['to'=>$row['email'],'subject'=>'Actividad en Goldensite Forum','body'=>"Tienes una notificación del foro. Abre el foro para leerla o cambiar tus preferencias.\n\n".$url];
        return $transport ? (bool)$transport('email',$message) : MailTransport::send($message['to'],$message['subject'],$message['body']);
    }

    public static function inbox(int $user): array
    {
        return Forum::db()->fetchAll('SELECT n.id,n.reason,n.read_at,t.id topic_id,t.slug,t.title FROM goldensite_forum_notifications n
            JOIN goldensite_forum_topics t ON t.id=n.topic_id AND t.deleted_at IS NULL
            LEFT JOIN goldensite_forum_replies r ON r.id=n.reply_id
            WHERE n.user_id=? AND (n.reply_id IS NULL OR (r.deleted_at IS NULL AND r.topic_id=t.id)) ORDER BY n.id DESC LIMIT 40', [$user]);
    }
}

