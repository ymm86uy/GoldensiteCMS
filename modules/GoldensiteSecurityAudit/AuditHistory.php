<?php
declare(strict_types=1);
namespace Modules\GoldensiteSecurityAudit;

use PDO;

final class AuditHistory
{
    public function __construct(private readonly PDO $db) {}

    public function recent(): array
    {
        return $this->db->query('SELECT id,created_at,cms_version,edition FROM goldensite_security_audits ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $query=$this->db->prepare('SELECT * FROM goldensite_security_audits WHERE id=?');
        $query->execute([$id]); $row=$query->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['report']=json_decode($row['report_json'],true,24,JSON_THROW_ON_ERROR);
        unset($row['report_json']);
        return $row;
    }

    public function save(array $report,int $actor,string $version,string $edition): array
    {
        $prior=$this->db->query('SELECT id FROM goldensite_security_audits ORDER BY id DESC LIMIT 1')->fetchColumn();
        $previous=$prior?$this->find((int)$prior):null;
        $report['comparison']=self::compare($report,$previous['report']??null);
        $report['comparison']['baseline_id']=$previous?(int)$previous['id']:null;
        $json=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (strlen($json)>1048576) throw new \RuntimeException('El informe supera el límite del historial.');
        $this->db->beginTransaction();
        try {
            $query=$this->db->prepare('INSERT INTO goldensite_security_audits (created_at,actor_id,cms_version,edition,report_json) VALUES (UTC_TIMESTAMP(),?,?,?,?)');
            $query->execute([$actor,$version,$edition,$json]);
            $id=(int)$this->db->lastInsertId();
            $cutoff=$this->db->query('SELECT id FROM goldensite_security_audits ORDER BY id DESC LIMIT 1 OFFSET 30')->fetchColumn();
            if ($cutoff!==false) $this->db->prepare('DELETE FROM goldensite_security_audits WHERE id<=?')->execute([(int)$cutoff]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return $this->find($id)??throw new \RuntimeException('No se pudo leer el informe guardado.');
    }

    public static function compare(array $current,?array $previous): array
    {
        $key=self::fingerprint(...);
        $before=[]; foreach (array_merge($previous['findings']??[],$previous['comparison']['unverified']??[]) as $finding) $before[$key($finding)]=$finding;
        $after=[]; foreach ($current['findings']??[] as $finding) $after[$key($finding)]=$finding;
        $result=['first'=>$previous===null,'new'=>[],'persistent'=>[],'resolved'=>[],'unverified'=>[]];
        foreach ($after as $id=>$finding) $result[isset($before[$id])?'persistent':'new'][]=$finding;
        foreach ($before as $id=>$finding) {
            if (isset($after[$id])) continue;
            $checked=($current['coverage'][$finding['area']??'unknown']??false)===true;
            $result[$checked?'resolved':'unverified'][]=$finding;
        }
        return $result;
    }

    public static function fingerprint(array $item): string
    {
        return hash('sha256',json_encode([$item['area']??'unknown',$item['title'],$item['location']],JSON_THROW_ON_ERROR));
    }
}
