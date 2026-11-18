<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Models;

use Modules\GoldensiteForum\{Forum, Notifications};

final class TopicModel
{
    public const KINDS = ['discussion'=>'Discusión','question'=>'Pregunta','poll'=>'Encuesta','announcement'=>'Anuncio'];

    public static function url(array $topic): string { return '/forum/topic/'.$topic['id'].'/'.$topic['slug']; }

    public static function find(int $id, bool $lock = false): ?array
    {
        return Forum::db()->fetch('SELECT t.*,u.username FROM goldensite_forum_topics t
            LEFT JOIN users u ON u.id=t.author_id WHERE t.id=?'.($lock?' FOR UPDATE':''), [$id]);
    }

    public static function live(int $id, bool $write = false): array
    {
        $topic = self::find($id, $write);
        if (!$topic || $topic['deleted_at'] || ($write && $topic['closed'])) {
            throw new \InvalidArgumentException('El hilo no está disponible para esta acción.');
        }
        return $topic;
    }

    public static function listing(int $board, array $cursor, string $tag): array
    {
        $params = [$board];
        $sql = 'SELECT t.id,t.title,t.slug,t.kind,t.pinned,t.closed,t.active_at FROM goldensite_forum_topics t
            WHERE t.board_id=? AND t.deleted_at IS NULL';
        if ($tag !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM goldensite_forum_topic_tags x JOIN goldensite_forum_tags g ON g.id=x.tag_id WHERE x.topic_id=t.id AND g.name=?)';
            $params[] = $tag;
        }
        if ($cursor) {
            [$pin,$at,$id] = $cursor;
            $sql .= ' AND (t.pinned<? OR (t.pinned=? AND (t.active_at<? OR (t.active_at=? AND t.id<?))))';
            array_push($params,$pin,$pin,$at,$at,$id);
        }
        return Forum::db()->fetchAll($sql.' ORDER BY t.pinned DESC,t.active_at DESC,t.id DESC LIMIT 26', $params);
    }

    public static function create(int $board, int $author, string $title, string $body, string $kind, array $tags, array $options): array
    {
        $db = Forum::db();
        $slug = Forum::slug($title);
        $db->execute('INSERT INTO goldensite_forum_topics (board_id,author_id,title,slug,body,kind,pinned) VALUES (?,?,?,?,?,?,?)',
            [$board,$author,$title,$slug,$body,$kind,(int)($kind==='announcement')]);
        $id = (int)$db->getConnection()->lastInsertId();
        sort($tags,SORT_STRING);
        foreach ($tags as $tag) {
            $db->execute('INSERT INTO goldensite_forum_tags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=id', [$tag]);
            $db->execute('INSERT IGNORE INTO goldensite_forum_topic_tags (topic_id,tag_id) SELECT ?,id FROM goldensite_forum_tags WHERE name=?', [$id,$tag]);
        }
        foreach ($options as $option) $db->execute('INSERT INTO goldensite_forum_poll_options (topic_id,label) VALUES (?,?)', [$id,$option]);
        Notifications::record($id, null, $author, null, $body);
        return ['id'=>$id,'slug'=>$slug];
    }

    public static function interaction(int $topicId, int $actor, string $action, int $target): void
    {
        Forum::transaction(static function () use ($topicId,$actor,$action,$target): void {
            $topic = self::find($topicId, true);
            if (!$topic || $topic['deleted_at'] || ($topic['closed'] && $action!=='follow')) throw new \InvalidArgumentException('El hilo no está disponible para esta acción.');
            $db = Forum::db();
            switch ($action) {
                case 'vote':
                    if ($topic['kind'] !== 'poll' || !$db->fetch('SELECT id FROM goldensite_forum_poll_options WHERE topic_id=? AND id=?', [$topicId,$target])) throw new \InvalidArgumentException('Opción de encuesta inválida.');
                    if ($db->fetch('SELECT user_id FROM goldensite_forum_votes WHERE topic_id=? AND user_id=?', [$topicId,$actor])) throw new \InvalidArgumentException('Ya registraste tu voto.');
                    $db->execute('INSERT INTO goldensite_forum_votes (topic_id,option_id,user_id) VALUES (?,?,?)', [$topicId,$target,$actor]);
                    break;
                case 'follow':
                    if (!in_array($target,[0,1,2],true)) throw new \InvalidArgumentException('Preferencia inválida.');
                    if (!$target) $db->execute('DELETE FROM goldensite_forum_follows WHERE topic_id=? AND user_id=?', [$topicId,$actor]);
                    else $db->execute('INSERT INTO goldensite_forum_follows (topic_id,user_id,email_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE email_enabled=VALUES(email_enabled)', [$topicId,$actor,$target-1]);
                    break;
                case 'solution':
                    if ($topic['kind']!=='question' || ((int)$topic['author_id']!==$actor && !\App\Core\IdentityAccess::allows('forum.moderate'))) throw new \InvalidArgumentException('Solo el autor de la pregunta o moderación pueden elegir la solución.');
                    if (!ReplyModel::visible($topicId,$target)) throw new \InvalidArgumentException('Selecciona una respuesta visible de este hilo.');
                    $db->execute('UPDATE goldensite_forum_topics SET solution_id=? WHERE id=?', [(int)$topic['solution_id']===$target?null:$target,$topicId]);
                    break;
                default:
                    throw new \InvalidArgumentException('Acción desconocida.');
            }
        });
    }
}

