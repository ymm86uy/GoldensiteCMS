<?php
namespace App\Controllers;

use App\Core\{AdminPath,Auth,Booking,ConsoleView,Csrf,Database,FrontendEngine,RateLimiter,RequestGuard,SecretStore};

final class EventsController
{
    public function admin(): void
    {
        Auth::requireAdmin();
        $active = FrontendEngine::isModuleActive('events');
        $base = AdminPath::url('/admin/events');
        $notice = $_SESSION['event_admin_notice']??'';
        unset($_SESSION['event_admin_notice']);
        $db = Database::getInstance();
        if ($active && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action = ConsoleView::field($_POST,'action',20);
                if ($action==='create') {
                    $this->create($db);
                } elseif ($action==='checkin') {
                    $this->checkIn($db);
                } elseif ($action==='close') {
                    $db->execute('UPDATE goldensite_events SET active=0 WHERE id=?',[self::number('event',1,2147483647)]);
                } else {
                    throw new \InvalidArgumentException('Acción inválida.');
                }
                ConsoleView::audit('events_'.$action);
                $_SESSION['event_admin_notice'] = $action==='checkin'?'Entrada validada y registrada.':'Evento actualizado.';
                header('Location: '.$base,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $events = $active?$db->fetchAll("SELECT e.*,(SELECT COUNT(*) FROM goldensite_event_tickets t WHERE t.event_id=e.id AND t.state IN ('issued','checked')) reserved,
            (SELECT COUNT(*) FROM goldensite_event_tickets t WHERE t.event_id=e.id AND t.state='checked') checked
            FROM goldensite_events e ORDER BY starts_at DESC LIMIT 100"):[];
        ConsoleView::render('events',compact('active','base','notice','events'),'Goldensite Events & Tickets');
    }

    public function publicPage(): void
    {
        header('Cache-Control: private, no-store');
        $active = FrontendEngine::isModuleActive('events');
        if (!$active) http_response_code(404);
        $actor = Auth::id();
        $notice = $_SESSION['event_notice']??'';
        unset($_SESSION['event_notice']);
        $db = Database::getInstance();
        if ($active && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            if (!$actor) { header('Location: /login?return_to=%2Feventos',true,303); return; }
            try {
                if (!RateLimiter::consume('event-ticket:'.$actor,12,300)) throw new \InvalidArgumentException('Espera antes de volver a intentarlo.');
                $action = ConsoleView::field($_POST,'action',20);
                if (!in_array($action,['issue','cancel'],true)) throw new \InvalidArgumentException('Acción inválida.');
                $this->changeTicket($db,self::number('event',1,2147483647),$actor,$action);
                $_SESSION['event_notice'] = $action==='issue'?'Tu entrada está disponible.':'Entrada cancelada.';
                header('Location: /eventos',true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $events = $active?$db->fetchAll('SELECT * FROM goldensite_events WHERE active=1 AND starts_at>UTC_TIMESTAMP() ORDER BY starts_at LIMIT 50'):[];
        $tickets = $active&&$actor?$db->fetchAll("SELECT t.*,e.title,e.venue,e.starts_at,e.ends_at,e.active
            FROM goldensite_event_tickets t JOIN goldensite_events e ON e.id=t.event_id
            WHERE t.user_id=? AND t.state IN ('issued','checked') ORDER BY e.starts_at DESC LIMIT 30",[$actor]):[];
        $e = ConsoleView::escape(...);
        $csrfToken = Csrf::generate();
        $siteTitle = 'Eventos · Goldensite';
        ob_start();
        require dirname(__DIR__,2).'/views/frontend/events.php';
        $content = ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/main.php';
    }

    private function create(Database $db): void
    {
        $title = ConsoleView::field($_POST,'title',150);
        $venue = ConsoleView::field($_POST,'venue',150);
        $start = Booking::parse(ConsoleView::field($_POST,'start',16));
        $end = Booking::parse(ConsoleView::field($_POST,'end',16));
        if ($title==='' || $venue==='' || $start->getTimestamp()<=time() || $end<=$start) throw new \InvalidArgumentException('Completa el evento con lugar y fechas futuras válidas.');
        $db->execute('INSERT INTO goldensite_events (title,venue,starts_at,ends_at,capacity) VALUES (?,?,?,?,?)',[$title,$venue,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),self::number('capacity',1,10000)]);
    }

    private function checkIn(Database $db): void
    {
        $token = ConsoleView::field($_POST,'code',64);
        if (!preg_match('/^[a-f0-9]{48}$/D',$token)) throw new \InvalidArgumentException('El código de entrada es inválido.');
        $stmt = $db->getConnection()->prepare("UPDATE goldensite_event_tickets t JOIN goldensite_events e ON e.id=t.event_id
            SET t.state='checked',t.checked_by=?,t.checked_at=UTC_TIMESTAMP()
            WHERE t.code_hash=? AND t.state='issued' AND e.active=1
            AND UTC_TIMESTAMP() BETWEEN DATE_SUB(e.starts_at,INTERVAL 2 HOUR) AND e.ends_at");
        $stmt->execute([Auth::id(),hash('sha256',$token)]);
        if ($stmt->rowCount()!==1) throw new \InvalidArgumentException('Entrada usada, cancelada o fuera del horario de validación.');
    }

    private function changeTicket(Database $db,int $eventId,int $user,string $action): void
    {
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $event = $db->fetch('SELECT id,capacity FROM goldensite_events WHERE id=? AND active=1 AND starts_at>UTC_TIMESTAMP() FOR UPDATE',[$eventId]);
            if (!$event) throw new \InvalidArgumentException('El evento ya no admite cambios.');
            $ticket = $db->fetch('SELECT id,state FROM goldensite_event_tickets WHERE event_id=? AND user_id=? FOR UPDATE',[$eventId,$user]);
            if ($action==='cancel') {
                if (!$ticket || $ticket['state']!=='issued') throw new \InvalidArgumentException('Esa entrada no admite cancelación.');
                $db->execute("UPDATE goldensite_event_tickets SET state='cancelled' WHERE id=?",[$ticket['id']]);
            } elseif (!$ticket || $ticket['state']==='cancelled') {
                $reserved = (int)$db->fetch("SELECT COUNT(*) total FROM goldensite_event_tickets WHERE event_id=? AND state IN ('issued','checked')",[$eventId])['total'];
                if ($reserved>=(int)$event['capacity']) throw new \InvalidArgumentException('Se agotaron las entradas.');
                $token = bin2hex(random_bytes(24));
                if ($ticket) {
                    $db->execute("UPDATE goldensite_event_tickets SET code_hash=?,sealed_code=?,state='issued' WHERE id=?",[hash('sha256',$token),SecretStore::seal($token),$ticket['id']]);
                } else {
                    $db->execute('INSERT INTO goldensite_event_tickets (event_id,user_id,code_hash,sealed_code) VALUES (?,?,?,?)',[$eventId,$user,hash('sha256',$token),SecretStore::seal($token)]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    private static function number(string $field,int $min,int $max): int
    {
        $value = filter_var($_POST[$field]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
        if ($value===false) throw new \InvalidArgumentException('Revisa los valores numéricos.');
        return $value;
    }
}
