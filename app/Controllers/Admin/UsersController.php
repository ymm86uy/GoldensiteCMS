<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath,Auth,ConsoleView,Database,IdentityAccess,OwnerAccess,ProductEdition,RequestGuard};
final class UsersController
{
    public function save(): void {
        Auth::requireAdmin(); RequestGuard::post(); $db=Database::getInstance();
        try {
            if (!IdentityAccess::allows('users.manage')) { http_response_code(403); exit('Acceso denegado.'); }
            $action=ConsoleView::field($_POST,'action',25);
            if ($action==='delete' && !IdentityAccess::superuser()) { http_response_code(403); exit('Solo un administrador completo puede eliminar cuentas.'); }
            ConsoleView::password();
            $id=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $message='Cuenta actualizada.';
            if ($action==='create') {
                $name=ConsoleView::field($_POST,'username',80); $email=ConsoleView::field($_POST,'email',150); $password=ConsoleView::field($_POST,'new_password',72); $role=ConsoleView::field($_POST,'role',10)?:'user';
                if (!preg_match('/^[\p{L}\p{N}_.-]{3,80}$/uD',$name)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||!in_array($role,['user','admin'],true)) throw new \InvalidArgumentException('Revisa usuario, correo y contraseña de al menos 12 caracteres.');
                if ($role==='admin'&&!IdentityAccess::superuser()) throw new \InvalidArgumentException('Solo un administrador completo puede crear administradores.');
                $db->execute('INSERT INTO users (username,name,email,password,role,role_id,status) VALUES (?,?,?,?,?,?,?)',[$name,$name,$email,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$role,$role==='admin'?1:2,'active']);
                ConsoleView::audit('user_created',$name);
            } elseif (in_array($action,['block','unblock'],true)) {
                if ($id===false) throw new \InvalidArgumentException('Cuenta inexistente.');
                $target=$db->fetch('SELECT id,role,role_id FROM users WHERE id=?',[$id]); if (!$target) throw new \InvalidArgumentException('Cuenta inexistente.');
                if ($id===(int)Auth::id()) throw new \InvalidArgumentException('No puedes bloquear tu propia cuenta.');
                $owner=ProductEdition::current() === 'owner' ? OwnerAccess::configuration() : null;
                if ($owner && $id===(int)$owner['user_id']) throw new \InvalidArgumentException('La cuenta Owner no se puede bloquear desde el directorio.');
                if (IdentityAccess::superuser($target)&&!IdentityAccess::superuser()) throw new \InvalidArgumentException('No puedes modificar una cuenta con privilegios superiores.');
                $db->execute('UPDATE users SET status=? WHERE id=?',[$action==='block'?'blocked':'active',$id]); if ($action==='block') $db->execute('DELETE FROM active_sessions WHERE user_id=?',[$id]); ConsoleView::audit('user_'.$action,'Cuenta #'.$id);
            } elseif ($action==='delete') {
                if ($id===false) throw new \InvalidArgumentException('Cuenta inexistente.');
                $this->deleteUser($db,$id);
                ConsoleView::audit('user_deleted','Cuenta #'.$id);
                $message='Usuario eliminado y sesiones cerradas.';
            } else throw new \InvalidArgumentException('Acción desconocida.');
            $_SESSION['dashboard_msg']=['type'=>'success','msg'=>$message];
            header('Location: '.AdminPath::url('/admin/dashboard'),true,$action==='delete'?303:302);
        } catch (\InvalidArgumentException $e) { http_response_code(422); ConsoleView::render('operation_error',['message'=>$e->getMessage()],'Revisar operación'); }
        catch (\PDOException $e) {
            http_response_code(422);
            $error=($action??null)==='delete'?'No se pudo eliminar la cuenta. Los cambios se revirtieron.':'El usuario o correo ya existe, o los datos no pudieron guardarse.';
            ConsoleView::render('operation_error',['message'=>$error],'Revisar operación');
        }
    }

    private function deleteUser(Database $db,int $id): void
    {
        if ($id===Auth::id()) throw new \InvalidArgumentException('No puedes eliminar tu propia cuenta.');
        $owner=ProductEdition::current()==='owner' ? OwnerAccess::configuration() : null;
        if ($owner && $id===(int)$owner['user_id']) throw new \InvalidArgumentException('La cuenta Owner no se puede eliminar desde el directorio.');

        $connection=$db->getConnection();
        $connection->beginTransaction();
        try {
            if (!$db->fetch('SELECT id FROM users WHERE id=? FOR UPDATE',[$id])) {
                throw new \InvalidArgumentException('La cuenta ya no existe.');
            }
            if ($db->fetch('SELECT id FROM goldensite_scim_users WHERE user_id=? AND deleted=0',[$id])) {
                throw new \InvalidArgumentException('Esta cuenta se administra desde su proveedor de identidad mediante SCIM.');
            }
            $db->execute('DELETE FROM active_sessions WHERE user_id=?',[$id]);
            $db->execute('DELETE FROM goldensite_identity_links WHERE user_id=?',[$id]);
            $db->execute('DELETE FROM goldensite_access_members WHERE user_id=?',[$id]);
            $db->execute('DELETE FROM goldensite_scim_users WHERE user_id=?',[$id]);
            $db->execute('DELETE FROM goldensite_community_profiles WHERE user_id=?',[$id]);
            $db->execute('UPDATE goldensite_messages SET ticket_assignee=NULL WHERE ticket_assignee=?',[$id]);
            $db->execute("UPDATE goldensite_vendors SET status='suspended' WHERE user_id=?",[$id]);
            $db->execute('DELETE FROM users WHERE id=?',[$id]);
            $connection->commit();
        } catch (\Throwable $error) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
}
