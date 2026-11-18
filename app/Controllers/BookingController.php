<?php
namespace App\Controllers;

use App\Core\{AdminPath,Auth,Booking,ConsoleView,Csrf,Database,FrontendEngine,RateLimiter,RequestGuard};

final class BookingController
{
    public function admin(): void
    {
        Auth::requireAdmin();
        $base = AdminPath::url('/admin/booking');
        $active = FrontendEngine::isModuleActive('booking');
        $notice = $_SESSION['booking_admin_notice']??'';
        unset($_SESSION['booking_admin_notice']);
        $db = Database::getInstance();
        if ($active && $_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action = ConsoleView::field($_POST,'action',20);
                if ($action==='professional') {
                    $name = ConsoleView::field($_POST,'name',100);
                    if ($name==='' || $db->fetch('SELECT id FROM goldensite_booking_professionals WHERE name=?',[$name])) {
                        throw new \InvalidArgumentException('Introduce un nombre de profesional que aún no exista.');
                    }
                    $db->execute('INSERT INTO goldensite_booking_professionals (name) VALUES (?)',[$name]);
                } elseif ($action==='slot') {
                    Booking::slot(self::id('professional'),ConsoleView::field($_POST,'service',150),ConsoleView::field($_POST,'start',16),ConsoleView::field($_POST,'end',16));
                } elseif ($action==='close') {
                    $stmt = $db->getConnection()->prepare('UPDATE goldensite_booking_availability SET closed=1 WHERE id=? AND appointment_id IS NULL AND closed=0');
                    $stmt->execute([self::id('slot')]);
                    if ($stmt->rowCount()!==1) throw new \InvalidArgumentException('El horario ya está retirado o tiene una cita activa.');
                } elseif ($action==='cancel') {
                    Booking::cancel(self::id('appointment'),null);
                } else {
                    throw new \InvalidArgumentException('Operación desconocida.');
                }
                ConsoleView::audit('booking_'.$action);
                $_SESSION['booking_admin_notice'] = 'Agenda actualizada.';
                header('Location: '.$base,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $professionals = $active?$db->fetchAll('SELECT * FROM goldensite_booking_professionals ORDER BY name'):[];
        $slots = $active?$db->fetchAll("SELECT s.*,p.name professional,a.client_name,a.client_phone
            FROM goldensite_booking_availability s JOIN goldensite_booking_professionals p ON p.id=s.professional_id
            LEFT JOIN goldensite_appointments a ON a.id=s.appointment_id WHERE s.closed=0 AND s.starts_at>UTC_TIMESTAMP()
            ORDER BY s.starts_at LIMIT 100"):[];
        ConsoleView::render('booking',compact('base','active','notice','professionals','slots'),'Goldensite Booking & Appointments');
    }

    public function publicPage(): void
    {
        header('Cache-Control: private, no-store');
        $available = FrontendEngine::isModuleActive('booking');
        if (!$available) http_response_code(404);
        $buyer = Auth::id();
        $notice = $_SESSION['booking_notice']??'';
        unset($_SESSION['booking_notice']);
        $db = Database::getInstance();
        if ($available && $_SERVER['REQUEST_METHOD']==='POST') {
            if (!$buyer) { header('Location: /login?return_to=%2Freservas',true,303); return; }
            RequestGuard::post();
            try {
                if (!RateLimiter::consume('booking:'.$buyer,12,300)) throw new \InvalidArgumentException('Espera antes de volver a intentarlo.');
                $action = ConsoleView::field($_POST,'action',20);
                if ($action==='reserve') {
                    Booking::reserve(self::id('slot'),$buyer,ConsoleView::field($_POST,'phone',30));
                    $_SESSION['booking_notice'] = 'Tu cita está confirmada.';
                } elseif ($action==='cancel') {
                    Booking::cancel(self::id('appointment'),$buyer);
                    $_SESSION['booking_notice'] = 'Cita cancelada. El horario vuelve a estar disponible.';
                } else {
                    throw new \InvalidArgumentException('Operación desconocida.');
                }
                header('Location: /reservas',true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $notice = $error->getMessage();
            }
        }
        $slots = $available?$db->fetchAll("SELECT s.*,p.name professional FROM goldensite_booking_availability s
            JOIN goldensite_booking_professionals p ON p.id=s.professional_id
            WHERE s.closed=0 AND s.appointment_id IS NULL AND s.starts_at>UTC_TIMESTAMP() ORDER BY s.starts_at LIMIT 50"):[];
        $mine = $available&&$buyer?$db->fetchAll("SELECT a.id,a.status,a.service_requested,s.starts_at,s.ends_at,p.name professional,
            (s.starts_at>UTC_TIMESTAMP() AND a.status='confirmed') can_cancel
            FROM goldensite_appointments a JOIN goldensite_booking_availability s ON s.id=a.availability_id
            JOIN goldensite_booking_professionals p ON p.id=s.professional_id WHERE a.account_id=? ORDER BY a.id DESC LIMIT 30",[$buyer]):[];
        $e = ConsoleView::escape(...);
        $csrfToken = Csrf::generate();
        $siteTitle = 'Reservas · Goldensite';
        ob_start();
        require dirname(__DIR__,2).'/views/frontend/booking.php';
        $content = ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/main.php';
    }

    private static function id(string $key): int
    {
        $id = filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if ($id===false) throw new \InvalidArgumentException('Identificador inválido.');
        return $id;
    }
}
