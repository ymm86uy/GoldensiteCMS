<?php
namespace App\Core;

final class EditorialHistory
{
    public const LIMIT = 30;
    public const FIELDS = ['title'=>'Título','slug'=>'Dirección','status'=>'Estado','meta_title'=>'Título SEO','meta_description'=>'Descripción SEO','content'=>'Contenido HTML'];

    public static function fingerprint(array $row): string
    {
        return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
    }

    public static function list(string $entity,int $id): array
    {
        return Database::getInstance()->fetchAll('SELECT id,author_name,created_at,title,status FROM goldensite_editorial_history WHERE entity=? AND document_id=? ORDER BY id DESC LIMIT '.self::LIMIT,[$entity,$id]);
    }

    public static function find(string $entity,int $id,int $version): ?array
    {
        return Database::getInstance()->fetch('SELECT * FROM goldensite_editorial_history WHERE entity=? AND document_id=? AND id=?',[$entity,$id,$version]);
    }

    public static function record(string $entity,array $row,?array $author): void
    {
        $data=[];
        foreach (self::FIELDS as $key=>$label) $data[$key]=(string)($row[$key]??'');
        $hash=self::fingerprint($data);
        $db=Database::getInstance();
        $id=(int)$row['id'];
        $latest=$db->fetch('SELECT fingerprint FROM goldensite_editorial_history WHERE entity=? AND document_id=? ORDER BY id DESC LIMIT 1',[$entity,$id]);
        if (($latest['fingerprint']??null)===$hash) return;
        $db->execute('INSERT INTO goldensite_editorial_history (entity,document_id,author_id,author_name,created_at,title,status,fingerprint,snapshot_json) VALUES (?,?,?,?,?,?,?,?,?)',
            [$entity,$id,$author['id']??null,$author['username']??null,$author===null?($row['updated_at']??null):date('Y-m-d H:i:s'),$data['title'],$data['status'],$hash,json_encode($data,JSON_THROW_ON_ERROR)]);
        $last=$db->fetch('SELECT id FROM goldensite_editorial_history WHERE entity=? AND document_id=? ORDER BY id DESC LIMIT 1 OFFSET '.(self::LIMIT-1),[$entity,$id]);
        if ($last) $db->execute('DELETE FROM goldensite_editorial_history WHERE entity=? AND document_id=? AND id<?',[$entity,$id,$last['id']]);
    }

    public static function snapshot(array $version): array
    {
        return json_decode($version['snapshot_json'],true,8,JSON_THROW_ON_ERROR);
    }

    public static function remove(string $entity,int $id): void
    {
        Database::getInstance()->execute('DELETE FROM goldensite_editorial_history WHERE entity=? AND document_id=?',[$entity,$id]);
    }
}
