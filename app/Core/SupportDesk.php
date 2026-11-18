<?php
namespace App\Core;

final class SupportDesk
{
    public const STATES = ['open'=>'Abierto','working'=>'En atención','waiting'=>'En espera','closed'=>'Cerrado'];
    public const PRIORITIES = ['low'=>'Baja','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'];

    public static function team(Database $db): array
    {
        $users = $db->fetchAll("SELECT u.id,u.username,u.role,u.role_id,r.permissions_json
            FROM users u LEFT JOIN goldensite_access_members m ON m.user_id=u.id
            LEFT JOIN goldensite_access_roles r ON r.id=m.role_id
            WHERE u.status='active' AND (u.role='admin' OR u.role_id=1 OR r.id IS NOT NULL) ORDER BY u.username");
        return array_values(array_filter($users,static function(array $user): bool {
            $permissions = json_decode($user['permissions_json']??'[]',true);
            return IdentityAccess::superuser($user) || (is_array($permissions) && in_array('messages.write',$permissions,true));
        }));
    }

    public static function save(Database $db,int $messageId,array $form,int $actor): void
    {
        $state = ConsoleView::field($form,'ticket_state',16);
        $priority = ConsoleView::field($form,'ticket_priority',16);
        $assignee = filter_var($form['ticket_assignee']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $note = $form['ticket_note']??'';
        if (!isset(self::STATES[$state],self::PRIORITIES[$priority]) || $assignee===false
            || !is_string($note) || strlen($note)>4000 || !mb_check_encoding($note,'UTF-8') || str_contains($note,"\0")) {
            throw new \InvalidArgumentException('Revisa el estado, la prioridad, el responsable y la nota.');
        }
        if ($assignee && !in_array($assignee,array_map('intval',array_column(self::team($db),'id')),true)) {
            throw new \InvalidArgumentException('Selecciona un integrante activo del equipo de atención.');
        }
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $row = $db->fetch('SELECT ticket_state,ticket_priority,ticket_assignee FROM goldensite_messages WHERE id=? FOR UPDATE',[$messageId]);
            if (!$row) throw new \InvalidArgumentException('El ticket ya no existe.');
            $note = trim($note);
            if ($row['ticket_state']!==$state || $row['ticket_priority']!==$priority || (int)$row['ticket_assignee']!==$assignee || $note!=='') {
                $db->execute('UPDATE goldensite_messages SET ticket_state=?,ticket_priority=?,ticket_assignee=? WHERE id=?',[$state,$priority,$assignee?:null,$messageId]);
                $db->execute('INSERT INTO goldensite_ticket_history (message_id,actor_id,state,priority,assignee,note) VALUES (?,?,?,?,?,?)',
                    [$messageId,$actor,$state,$priority,$assignee?:null,$note?:null]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
