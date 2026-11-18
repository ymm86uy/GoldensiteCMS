<?php
namespace App\Geo;

use App\Core\Database;

final class ActivityMap
{
    public static function activeAccounts(): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT ip_address, last_activity >= NOW()-INTERVAL 5 MINUTE AS recent
             FROM (
                SELECT s.ip_address,s.last_activity,ROW_NUMBER() OVER (
                    PARTITION BY s.user_id ORDER BY s.last_activity DESC,s.session_id DESC
                ) AS sequence_number
                FROM active_sessions s JOIN users u ON u.id=s.user_id
                WHERE s.last_activity>=NOW()-INTERVAL 30 MINUTE AND u.status IN ('active','enabled','1')
             ) AS latest
             WHERE sequence_number=1 ORDER BY last_activity DESC LIMIT 2001"
        );
        return self::aggregate($rows,2000,false);
    }

    public static function waf(): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT ip_address,method,target_url,rule_ids,disposition,created_at,
                created_at>=NOW()-INTERVAL 5 MINUTE AS recent
             FROM goldensite_waf_logs
             WHERE created_at>=NOW()-INTERVAL 30 MINUTE AND disposition IN ('BLOCK','MONITOR')
             ORDER BY id DESC LIMIT 301"
        );
        return self::aggregate($rows,300,true);
    }

    private static function aggregate(array $rows,int $limit,bool $includeEvents): array
    {
        $lookup = new CountryLookup();
        $countries = [];
        $events = [];
        $unknown = 0;
        $recent = 0;
        $limited = count($rows)>$limit;
        $recentLimited = $limited && (bool)$rows[$limit]['recent'];
        $rows = array_slice($rows,0,$limit);
        foreach ($rows as $index=>$row) {
            $country = $lookup->country($row['ip_address']);
            if ($country) {
                $code = $country['code'];
                $countries[$code] ??= $country+['count'=>0];
                $countries[$code]['count']++;
            } else {
                $unknown++;
            }
            $recent += (int)$row['recent'];
            if ($includeEvents && $index<12) {
                unset($row['recent']);
                $events[] = $row+['country'=>$country];
            }
        }
        usort($countries,static fn(array $a,array $b): int=>($b['count']<=>$a['count']) ?: strcmp($a['code'],$b['code']));
        return [
            'available'=>$lookup->available(),'countries'=>$countries,'events'=>$events,'unknown'=>$unknown,
            'last_5'=>$recent,'last_30'=>count($rows),'limited'=>$limited,'last_5_limited'=>$recentLimited,
        ];
    }
}
