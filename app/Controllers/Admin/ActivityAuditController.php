<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database};
final class ActivityAuditController
{
    public function index(): void {
        Auth::requireAdmin(); $db=Database::getInstance(); $where=[]; $params=[]; $filters=[];
        foreach (['q','event','from','to'] as $key) $filters[$key]=is_string($_GET[$key]??null)?substr(trim($_GET[$key]),0,100):'';
        if ($filters['q']!=='') { $where[]='(username LIKE ? OR description LIKE ?)'; array_push($params,'%'.$filters['q'].'%','%'.$filters['q'].'%'); }
        if ($filters['event']!=='') { $where[]='action=?'; $params[]=$filters['event']; }
        foreach (['from'=>'>=','to'=>'<='] as $key=>$operator) if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$filters[$key])) { $where[]='created_at '.$operator.' ?'; $params[]=$filters[$key].($key==='from'?' 00:00:00':' 23:59:59'); }
        $sql=$where?' WHERE '.implode(' AND ',$where):''; $total=(int)$db->fetch('SELECT COUNT(*) n FROM goldensite_activity_logs'.$sql,$params)['n']; $page=max(1,min(10000,(int)($_GET['page']??1)));
        if (($_GET['export']??'')==='csv') { header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-activity.csv"'); $out=fopen('php://output','w'); fputcsv($out,['ID','Fecha','Cuenta','Evento','Descripción','IP'],',','"',''); foreach ($db->fetchAll('SELECT id,created_at,username,action,description,ip_address FROM goldensite_activity_logs'.$sql.' ORDER BY id DESC LIMIT 10000',$params) as $row) fputcsv($out,array_map(static fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v)?"'".$v:(string)$v,array_values($row)),',','"',''); fclose($out); return; }
        $logs=$db->fetchAll('SELECT * FROM goldensite_activity_logs'.$sql.' ORDER BY id DESC LIMIT 30 OFFSET '.(($page-1)*30),$params); $events=$db->fetchAll('SELECT DISTINCT action FROM goldensite_activity_logs ORDER BY action LIMIT 200');
        ConsoleView::render('activity',['logs'=>$logs,'events'=>$events,'filters'=>$filters,'total'=>$total,'page'=>$page],'Registro de actividad · Goldensite');
    }
}
