<?php
namespace App\Core;

final class Booking
{
    public static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(Settings::get('site_timezone','America/Montevideo'));
    }

    public static function date(string $utc): string
    {
        return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(self::timezone())->format('d/m/Y H:i T');
    }

    public static function slot(int $professional,string $service,string $start,string $end): void
    {
        $from = self::parse($start);
        $until = self::parse($end);
        $duration = $until->getTimestamp()-$from->getTimestamp();
        if ($service==='' || $duration<300 || $duration>28800 || $from->getTimestamp()<=time() || $from->getTimestamp()>time()+31536000) {
            throw new \InvalidArgumentException('Selecciona un servicio y un horario futuro de entre 5 minutos y 8 horas.');
        }
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            if (!$db->fetch('SELECT id FROM goldensite_booking_professionals WHERE id=? FOR UPDATE',[$professional])) {
                throw new \InvalidArgumentException('Profesional inexistente.');
            }
            $begin = $from->format('Y-m-d H:i:s');
            $finish = $until->format('Y-m-d H:i:s');
            if ($db->fetch('SELECT id FROM goldensite_booking_availability WHERE professional_id=? AND (closed=0 OR appointment_id IS NOT NULL) AND starts_at<? AND ends_at>? LIMIT 1',[$professional,$finish,$begin])) {
                throw new \InvalidArgumentException('Ese profesional ya tiene disponibilidad o una reserva que se solapa.');
            }
            $db->execute('INSERT INTO goldensite_booking_availability (professional_id,service,starts_at,ends_at) VALUES (?,?,?,?)',[$professional,$service,$begin,$finish]);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function reserve(int $slot,int $account,string $phone): void
    {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $user = $db->fetch("SELECT id,name,username FROM users WHERE id=? AND status='active' FOR UPDATE",[$account]);
            $row = $db->fetch('SELECT * FROM goldensite_booking_availability WHERE id=? AND starts_at>UTC_TIMESTAMP() FOR UPDATE',[$slot]);
            if (!$user || !$row || (int)$row['closed'] || $row['appointment_id']!==null) {
                throw new \InvalidArgumentException('Ese horario ya no está disponible.');
            }
            if (!preg_match('/^[+0-9 ().-]{5,30}$/D',$phone) || !preg_match('/[0-9]{4}/',preg_replace('/[^0-9]/','',$phone))) {
                throw new \InvalidArgumentException('Escribe un teléfono de contacto válido.');
            }
            if ($db->fetch("SELECT a.id FROM goldensite_appointments a JOIN goldensite_booking_availability s ON s.id=a.availability_id
                WHERE a.account_id=? AND a.status='confirmed' AND s.starts_at<? AND s.ends_at>? LIMIT 1",
                [$account,$row['ends_at'],$row['starts_at']])) {
                throw new \InvalidArgumentException('Ya tienes otra cita en ese horario.');
            }
            $db->execute("INSERT INTO goldensite_appointments (client_name,client_phone,service_requested,booking_date,status,account_id,availability_id)
                VALUES (?,?,?,?,'confirmed',?,?)",[$user['name']?:$user['username'],$phone,$row['service'],$row['starts_at'],$account,$slot]);
            $db->execute('UPDATE goldensite_booking_availability SET appointment_id=? WHERE id=?',[$db->lastInsertId(),$slot]);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function cancel(int $id,?int $account): void
    {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $row = $db->fetch("SELECT a.id,a.account_id,s.id slot_id FROM goldensite_booking_availability s
                JOIN goldensite_appointments a ON a.id=s.appointment_id WHERE a.id=? AND a.status='confirmed' AND s.starts_at>UTC_TIMESTAMP() FOR UPDATE",[$id]);
            if (!$row || ($account!==null && (int)$row['account_id']!==$account)) {
                throw new \InvalidArgumentException('Esa cita no admite cancelación.');
            }
            $db->execute("UPDATE goldensite_appointments SET status='cancelled' WHERE id=?",[$id]);
            $db->execute('UPDATE goldensite_booking_availability SET appointment_id=NULL WHERE id=?',[$row['slot_id']]);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function parse(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,self::timezone());
        if (!$date || $date->format('Y-m-d\TH:i')!==$value) throw new \InvalidArgumentException('La fecha y hora no son válidas.');
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
