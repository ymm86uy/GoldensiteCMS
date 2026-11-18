<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

use App\Core\Database;

final class Store
{
    private const ECHO_FIELDS='e.id,e.title,e.body,e.region,e.created_at,(e.audio_key IS NOT NULL) audio,
        (e.image_key IS NOT NULL) image,e.image_alt,(e.zone_row IS NOT NULL) approximate,(e.user_id=?) own,u.username,
        (SELECT COUNT(*) FROM goldensite_social_resonances r WHERE r.echo_id=e.id) halo';

    public static function db(): Database { return Database::getInstance(); }

    public static function feed(int $before,int $viewer): array
    {
        $db=self::db();
        $rows=$db->fetchAll('SELECT '.self::ECHO_FIELDS.'
            FROM goldensite_social_echoes e LEFT JOIN users u ON u.id=e.user_id
            WHERE e.hidden_at IS NULL AND (?=0 OR e.id<?) ORDER BY e.id DESC LIMIT 16',[$viewer,$before,$before]);
        $more=count($rows)>15;
        if ($more) array_pop($rows);
        return ['echoes'=>$rows,'before'=>$more?(int)end($rows)['id']:null];
    }

    public static function detail(int $id,int $viewer): array
    {
        $row=self::db()->fetch('SELECT '.self::ECHO_FIELDS.' FROM goldensite_social_echoes e
            LEFT JOIN users u ON u.id=e.user_id WHERE e.hidden_at IS NULL AND e.id=?',[$viewer,$id]);
        if (!$row) throw new \OutOfBoundsException('Eco no disponible.');
        return $row;
    }

    public static function map(): array
    {
        $rows=self::db()->fetchAll('SELECT id,title,region,zone_row,zone_col,
            (audio_key IS NOT NULL) audio,(image_key IS NOT NULL) image
            FROM goldensite_social_echoes WHERE hidden_at IS NULL AND region IS NOT NULL ORDER BY id DESC LIMIT 60');
        return array_map(static function (array $row): array {
            $row['point']=Location::point($row['zone_row'],$row['zone_col']);
            unset($row['zone_row'],$row['zone_col']);
            return $row;
        },$rows);
    }

    public static function mediaDirectory(): string
    {
        return dirname(__DIR__,2).'/storage/social';
    }

    public static function mediaPath(string $key): ?string
    {
        $dir=self::mediaDirectory();
        if (!preg_match('/^[a-f0-9]{32}$/D',$key) || is_link($dir)) return null;
        $path=$dir.'/'.$key;
        return is_file($path) && !is_link($path) ? $path : null;
    }

    public static function newMediaPath(string $key): string
    {
        $dir=self::mediaDirectory();
        if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir))) {
            throw new \RuntimeException('Almacén no disponible.');
        }
        return $dir.'/'.$key;
    }

    public static function removeMedia(?string $key): void
    {
        if ($key!==null && ($path=self::mediaPath($key))!==null) unlink($path);
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

