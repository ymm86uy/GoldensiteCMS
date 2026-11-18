<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Models;

use Modules\GoldensiteForum\{Forum, Notifications};

final class ReplyModel
{
    public static function visible(int $topic, int $id): ?array
    {
        return Forum::db()->fetch('SELECT * FROM goldensite_forum_replies WHERE topic_id=? AND id=? AND deleted_at IS NULL', [$topic,$id]);
    }

    public static function listing(int $topic, int $after): array
    {
        return Forum::db()->fetchAll('SELECT r.*,u.username,
            (SELECT COUNT(*) FROM goldensite_forum_reactions x WHERE x.reply_id=r.id AND x.reaction=\'like\') likes,
            (SELECT COUNT(*) FROM goldensite_forum_reactions x WHERE x.reply_id=r.id AND x.reaction=\'helpful\') helpful
            FROM goldensite_forum_replies r LEFT JOIN users u ON u.id=r.author_id
            WHERE r.topic_id=? AND r.deleted_at IS NULL AND r.id>? ORDER BY r.id LIMIT 26', [$topic,$after]);
    }

    public static function create(array $topic, int $author, string $body): int
    {
        $db = Forum::db();
        $db->execute('INSERT INTO goldensite_forum_replies (topic_id,author_id,body) VALUES (?,?,?)', [$topic['id'],$author,$body]);
        $id = (int)$db->getConnection()->lastInsertId();
        $db->execute('UPDATE goldensite_forum_topics SET active_at=NOW() WHERE id=?', [$topic['id']]);
        Notifications::record((int)$topic['id'],$id,$author,(int)$topic['author_id'],$body);
        return $id;
    }

    public static function react(int $topic, int $reply, int $actor, string $reaction): void
    {
        Forum::transaction(static function () use ($topic,$reply,$actor,$reaction): void {
            TopicModel::live($topic,true);
            $row = self::visible($topic,$reply);
            if (!$row || (int)$row['author_id']===$actor || !$row['author_id'] || !in_array($reaction,['like','helpful','remove'],true)) throw new \InvalidArgumentException('Reacción inválida.');
            $db = Forum::db();
            if ($reaction==='remove') $db->execute('DELETE FROM goldensite_forum_reactions WHERE reply_id=? AND user_id=?', [$reply,$actor]);
            else $db->execute('INSERT INTO goldensite_forum_reactions (reply_id,user_id,reaction) VALUES (?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction)', [$reply,$actor,$reaction]);
        });
    }
}

