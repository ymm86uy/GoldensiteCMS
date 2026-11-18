<?php
namespace App\Core;
final class DashboardData
{
    public static function summary(): array
    {
        $db=Database::getInstance();
        $stats=$db->fetch("SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM active_sessions WHERE last_activity >= NOW()-INTERVAL 15 MINUTE) AS sessions, (SELECT COUNT(*) FROM goldensite_pages WHERE status='published' AND is_published=1) AS pages, (SELECT COUNT(*) FROM goldensite_posts WHERE status='published') AS posts, (SELECT COUNT(*) FROM goldensite_messages WHERE status='unread') AS messages, (SELECT COUNT(*) FROM goldensite_waf_logs WHERE disposition='BLOCK' AND created_at>=NOW()-INTERVAL 24 HOUR) AS blocked");
        foreach ($stats as &$value) $value=(int)$value; unset($value);
        $stats['modules']=0; foreach (ModuleCatalog::all() as $m) if (FrontendEngine::isModuleActive($m['id'])) $stats['modules']++;
        return ['ok'=>true,'metrics'=>$stats,'updated_at'=>gmdate('c')];
    }
    public static function visits(): array
    {
        $db=Database::getInstance(); $today=$db->fetch('SELECT CURRENT_DATE AS today')['today'];
        $hits=$db->fetchAll('SELECT DATE(created_at) AS day, COUNT(*) AS total FROM goldensite_analytics_hits WHERE created_at >= CURRENT_DATE-INTERVAL 6 DAY GROUP BY DATE(created_at)');
        $map=array_column($hits,'total','day'); $days=[];
        for ($i=6;$i>=0;$i--) { $date=(new \DateTimeImmutable($today))->modify('-'.$i.' days')->format('Y-m-d'); $days[]=['day'=>$date,'total'=>(int)($map[$date]??0)]; }
        return $days;
    }
}
