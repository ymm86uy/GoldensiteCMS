<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

use App\Core\Database;

final class Store
{
    public static function db(): Database { return Database::getInstance(); }

    public static function feed(int $before): array
    {
        $db=self::db();
        $rows=$db->fetchAll('SELECT e.id,e.title,e.body,e.region,e.created_at,(e.audio_key IS NOT NULL) audio,u.username,
            (SELECT COUNT(*) FROM goldensite_social_resonances r WHERE r.echo_id=e.id) halo
            FROM goldensite_social_echoes e LEFT JOIN users u ON u.id=e.user_id
            WHERE e.hidden_at IS NULL AND (?=0 OR e.id<?) ORDER BY e.id DESC LIMIT 16',[$before,$before]);
        $more=count($rows)>15;
        if ($more) array_pop($rows);
        return ['echoes'=>$rows,'before'=>$more?(int)end($rows)['id']:null];
    }

    public static function visible(int $id,bool $lock=false): array
    {
        $row=self::db()->fetch('SELECT * FROM goldensite_social_echoes WHERE id=? AND hidden_at IS NULL'.($lock?' FOR UPDATE':''),[$id]);
        if (!$row) throw new \OutOfBoundsException('Eco no disponible.');
        return $row;
    }

    public static function interaction(int $id,int $user,string $kind,string|int $value): void
    {
        $db=self::db();
        $pdo=$db->getConnection();
        $pdo->beginTransaction();
        try {
            $echo=self::visible($id,true);
            if ($kind==='wave') $db->execute('INSERT INTO goldensite_social_waves (echo_id,user_id,body) VALUES (?,?,?)',[$id,$user,$value]);
            elseif ((int)$echo['user_id']!==$user) $db->execute('INSERT INTO goldensite_social_resonances (echo_id,user_id,attentive_seconds) VALUES (?,?,?) ON DUPLICATE KEY UPDATE attentive_seconds=GREATEST(attentive_seconds,VALUES(attentive_seconds))',[$id,$user,$value]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function prune(): void
    {
        self::db()->execute('DELETE FROM goldensite_social_presence WHERE expires_at<=UTC_TIMESTAMP()');
    }
}

