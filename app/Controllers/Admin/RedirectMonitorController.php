<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,RedirectRules,RequestGuard};
final class RedirectMonitorController
{
    public function index(): void { Auth::requireAdmin(); $db=Database::getInstance(); $status=$_SESSION['redirect_notice']??null; unset($_SESSION['redirect_notice']);
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try { $action=ConsoleView::field($_POST,'action',20); if ($action==='create') RedirectRules::save(ConsoleView::field($_POST,'source_url',255),ConsoleView::field($_POST,'target_url',255),(int)($_POST['redirect_type']??301)); elseif ($action==='delete') $db->execute('DELETE FROM goldensite_redirects WHERE id=?',[(int)($_POST['id']??0)]); else throw new \InvalidArgumentException('Acción desconocida.'); ConsoleView::audit('redirect_'.$action); $_SESSION['redirect_notice']='Reglas de redirección actualizadas.'; header('Location: /admin/monitor-redirects'); return; } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $q=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):''; $page=max(1,min(10000,(int)($_GET['page']??1))); $where=$q!==''?' WHERE url LIKE ?':''; $params=$q!==''?['%'.$q.'%']:[];
        $logs=$db->fetchAll('SELECT url,SUM(hits) hits,MAX(last_detected) last_detected FROM goldensite_404_logs'.$where.' GROUP BY url ORDER BY last_detected DESC LIMIT 25 OFFSET '.(($page-1)*25),$params);
        ConsoleView::render('redirect_monitor',['rules'=>$db->fetchAll('SELECT * FROM goldensite_redirects ORDER BY id DESC LIMIT 500'),'logs'=>$logs,'q'=>$q,'page'=>$page,'status'=>$status],'Rutas y redirecciones · Goldensite');
    }
}
