<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum;

final class Reputation
{
    public static function summary(array $authors): array
    {
        $authors=array_values(array_unique(array_filter(array_map('intval',$authors))));
        if (!$authors) return [];
        $slots=implode(',',array_fill(0,count($authors),'?'));
        $rows=Forum::db()->fetchAll("SELECT u.id,u.role,u.role_id,a.permissions_json,
            COALESCE((SELECT SUM(IF(x.reaction='helpful',2,1)) FROM goldensite_forum_replies r
                JOIN goldensite_forum_topics t ON t.id=r.topic_id AND t.deleted_at IS NULL
                JOIN goldensite_forum_reactions x ON x.reply_id=r.id WHERE r.author_id=u.id AND r.deleted_at IS NULL),0)
            + 5*(SELECT COUNT(*) FROM goldensite_forum_replies r JOIN goldensite_forum_topics t
                ON t.id=r.topic_id AND t.solution_id=r.id AND t.deleted_at IS NULL
                WHERE r.author_id=u.id AND r.deleted_at IS NULL AND (t.author_id IS NULL OR t.author_id<>u.id)) points
            FROM users u LEFT JOIN goldensite_access_members m ON m.user_id=u.id
            LEFT JOIN goldensite_access_roles a ON a.id=m.role_id WHERE u.id IN ($slots)",$authors);
        $result=[];
        foreach ($rows as $row) {
            $badges=[];
            if ($row['role']==='admin' || (int)$row['role_id']===1 || in_array('forum.moderate',json_decode($row['permissions_json']??'[]',true)?:[],true)) $badges[]='Moderación';
            if ((int)$row['points']>=10) $badges[]='Colaborador';
            if ((int)$row['points']>=50) $badges[]='Referente';
            $result[(int)$row['id']]=['points'=>(int)$row['points'],'badges'=>$badges];
        }
        return $result;
    }
}

