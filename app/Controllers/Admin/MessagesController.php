<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath,Auth,ConsoleView,Database,FrontendEngine,MailTransport,RateLimiter,RequestGuard,SupportDesk};
final class MessagesController
{
    public function index(): void {
        Auth::requireAdmin(); $db=Database::getInstance(); $status=null;
        $desk = FrontendEngine::isModuleActive('support-desk');
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $id=filter_var($_POST['message_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false || !$db->fetch('SELECT id FROM goldensite_messages WHERE id=?',[$id])) throw new \InvalidArgumentException('Mensaje inexistente.');
            $action=ConsoleView::field($_POST,'action',30);
            if ($action==='ticket' && $desk) {
                SupportDesk::save($db,$id,$_POST,(int)Auth::id());
                $status = 'Ticket actualizado.';
            }
            elseif (in_array($action,['mark_read','archive','unread'],true)) $db->execute('UPDATE goldensite_messages SET status=? WHERE id=?',[['mark_read'=>'read','archive'=>'archived','unread'=>'unread'][$action],$id]);
            elseif ($action==='delete') {
                ConsoleView::password();
                $this->withLockedMessage($db,$id,static function () use ($db,$id): void {
                    $db->execute('DELETE FROM goldensite_message_replies WHERE message_id=?',[$id]);
                    $db->execute('DELETE FROM goldensite_ticket_history WHERE message_id=?',[$id]);
                    $db->execute('DELETE FROM goldensite_messages WHERE id=?',[$id]);
                });
                $status='Conversación eliminada.';
            }
            elseif (in_array($action,['reply','draft'],true)) {
                $reply=ConsoleView::field($_POST,'reply_message',20000); if ($reply==='') throw new \InvalidArgumentException('Escribe una respuesta.');
                $state=$this->withLockedMessage($db,$id,static function (array $row) use ($db,$id,$action,$reply): string {
                    $state='draft';
                    if ($action==='reply') {
                        if (!RateLimiter::consume('message-send:'.Auth::id(),10,300)) throw new \InvalidArgumentException('Espera antes de enviar más respuestas.');
                        $state=MailTransport::send($row['email'],'Re: '.$row['subject'],$reply)?'sent':'failed';
                    }
                    $db->execute('INSERT INTO goldensite_message_replies (message_id,body,delivery_state,created_by) VALUES (?,?,?,?)',[$id,$reply,$state,Auth::id()]);
                    if ($state==='sent') $db->execute("UPDATE goldensite_messages SET reply_message=?,status='replied',replied_at=NOW() WHERE id=?",[$reply,$id]);
                    return $state;
                });
                $status=$state==='sent'?'Respuesta aceptada por el servidor SMTP.':($state==='failed'?'No se pudo enviar. La respuesta quedó guardada con estado de error. Revisa SMTP.':'Borrador guardado.');
            } else throw new \InvalidArgumentException('Acción desconocida.');
            ConsoleView::audit('message_'.$action,'Mensaje #'.$id);
            $_SESSION['message_notice']=$status??'Mensaje actualizado.';
            $inbox=AdminPath::url('/admin/messages');
            if ($action==='delete') {
                $path=AdminPath::normalize(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'');
                if ($path==='/admin/support-desk') $inbox=AdminPath::url($path);
                $filters=array_filter(array_intersect_key($_GET,array_flip(['q','state','ticket'])),'is_string');
                header('Location: '.$inbox.($filters?'?'.http_build_query($filters):''),true,303);
                return;
            }
            header('Location: '.$inbox.'?id='.$id); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } }
        $status??=$_SESSION['message_notice']??null; unset($_SESSION['message_notice']);
        $q=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):''; $filter=is_string($_GET['state']??null)?$_GET['state']:''; $where=[]; $params=[];
        if (in_array($filter,['unread','read','replied','archived'],true)) { $where[]='status=?'; $params[]=$filter; } if ($q!=='') { $where[]='(name LIKE ? OR email LIKE ? OR subject LIKE ?)'; array_push($params,...array_fill(0,3,'%'.$q.'%')); }
        $ticketFilter = is_string($_GET['ticket']??null) ? $_GET['ticket'] : '';
        if ($desk && isset(SupportDesk::STATES[$ticketFilter])) { $where[]='ticket_state=?'; $params[]=$ticketFilter; }
        $sql=$where?' WHERE '.implode(' AND ',$where):''; $page=max(1,min(10000,(int)($_GET['page']??1))); $total=(int)$db->fetch('SELECT COUNT(*) n FROM goldensite_messages'.$sql,$params)['n'];
        $messages=$db->fetchAll('SELECT id,name,email,subject,status,created_at,ticket_state,ticket_priority FROM goldensite_messages'.$sql.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20),$params);
        $selected=$db->fetch('SELECT * FROM goldensite_messages WHERE id=?',[(int)($_GET['id']??0)]); $replies=$selected?$db->fetchAll('SELECT body,delivery_state,created_at FROM goldensite_message_replies WHERE message_id=? ORDER BY id DESC',[$selected['id']]):[];
        $history = $desk&&$selected?$db->fetchAll('SELECT h.*,u.username actor,v.username responsible FROM goldensite_ticket_history h LEFT JOIN users u ON u.id=h.actor_id LEFT JOIN users v ON v.id=h.assignee WHERE h.message_id=? ORDER BY h.id DESC LIMIT 50',[$selected['id']]):[];
        $team = $desk&&$selected?SupportDesk::team($db):[];
        ConsoleView::render('messages',compact('messages','selected','replies','status','q','filter','page','total','desk','history','team','ticketFilter'),$desk?'Goldensite Support Desk':'Buzón de mensajes · Goldensite');
    }

    private function withLockedMessage(Database $db,int $id,callable $operation): mixed
    {
        $connection=$db->getConnection();
        $connection->beginTransaction();
        try {
            $row=$db->fetch('SELECT * FROM goldensite_messages WHERE id=? FOR UPDATE',[$id]);
            if (!$row) throw new \InvalidArgumentException('El mensaje ya no existe.');
            $result=$operation($row);
            $connection->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
}
