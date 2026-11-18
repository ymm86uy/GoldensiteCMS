<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath,Auth,CacheStore,Csrf,Database,DatabaseBackup,Environment,MailTransport,RequestGuard,SecretStore,Settings,SiteConfiguration};
final class SettingsController
{
    public function index(?array $feedback = null): void
    {
        Auth::requireAdmin();
        if (isset($_GET['action'])) { RequestGuard::post(); $this->save(); return; }
        $settings=Settings::all(); $smtpConfigured=($settings['smtp_pass']??'')!==''; unset($settings['smtp_pass']);
        $externalPlan=null; $externalToken=$_SESSION['external_import_token']??''; if (is_string($externalToken)&&$externalToken!=='') { try { $externalPlan=\App\Core\CmsMigration::load($externalToken); } catch (\Throwable $e) { unset($_SESSION['external_import_token']); } }
        $status=$feedback??$_SESSION['settings_msg']??null; unset($_SESSION['settings_msg']);
        $appUrl=Environment::get('APP_URL'); $databaseVersion=Database::getInstance()->getConnection()->query('SELECT VERSION()')->fetchColumn();
        $title='Configuración del sistema · Goldensite'; $csrfToken=Csrf::generate();
        ob_start(); require dirname(__DIR__,3).'/views/admin/settings.php'; $content=ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }
    public function save(): void
    {
        Auth::requireAdmin(); RequestGuard::post();
        $action=$_GET['action']??$_POST['action']??'save';
        if (in_array($action,['export_db','restore','import_external_analyze','import_external_confirm'],true)&&!\App\Core\IdentityAccess::superuser()) { http_response_code(403); exit('La copia, restauración e importación externa requieren un administrador completo.'); }
        try {
            if ($action==='test_smtp_delivery') {
                if (!\App\Core\IdentityAccess::superuser()) { http_response_code(403); return; }
                $recipient=Settings::get('admin_email');
                if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Guarda un correo administrativo válido.');
                if (!\App\Core\RateLimiter::consume('smtp-delivery-test',3,600)) throw new \InvalidArgumentException('Espera unos minutos antes de volver a enviar.');
                $reference=strtoupper(bin2hex(random_bytes(4)));
                $sent=MailTransport::send($recipient,'Prueba de entrega GoldenSiteCMS '.$reference,'Si recibiste este correo, la entrega funcionó. Referencia '.$reference.'.');
                $_SESSION['settings_msg']=$sent?['type'=>'success','msg'=>'El servidor SMTP aceptó el mensaje '.$reference.'. Confirma su llegada en el buzón administrativo.']:['type'=>'warning','msg'=>'El servidor SMTP no aceptó la prueba. Revisa las credenciales, el remitente y el registro del servidor.'];
                header('Location: '.\App\Core\AdminPath::url('/admin/settings#correo'),true,303); return;
            }
            if ($action==='import_external_analyze') { unset($_SESSION['external_import_token']); $sql=\App\Core\CmsDumpReader::readUpload($_FILES['external_sql_file']??[]); $plan=\App\Core\CmsMigration::plan($sql,\App\Core\ConsoleView::field($_POST,'source_cms',20),($_POST['import_users']??'')==='1'); $_SESSION['external_import_token']=\App\Core\CmsMigration::save($plan); header('Location: /admin/settings#external-import'); return; }
            if ($action==='import_external_confirm') { $this->verifyPassword(); if (($_POST['import_confirmation']??'')!=='IMPORTAR') throw new \InvalidArgumentException('Escribe IMPORTAR para confirmar la conversión.'); $token=\App\Core\ConsoleView::field($_POST,'import_token',64); $summary=\App\Core\CmsMigration::apply($token); \App\Core\ConsoleView::audit('external_database_imported','Conversión de contenido completada.'); $_SESSION['settings_msg']=['type'=>'success','msg'=>'Importación completada: '.$summary['pages'].' páginas, '.$summary['posts'].' artículos y '.$summary['users'].' usuarios. Respaldo previo privado: '.$summary['backup']]; unset($_SESSION['external_import_token']); header('Location: /admin/settings#external-import'); return; }
            if ($action==='site_icon') { $url=\App\Core\SiteIcon::upload($_FILES['site_icon_file']??[]); try { Settings::set('site_icon',$url); } catch (\Throwable $e) { @unlink(dirname(__DIR__,3).'/public'.$url); throw $e; } CacheStore::clear(); $_SESSION['settings_msg']=['type'=>'success','msg'=>'Icono global actualizado.']; header('Location: /admin/settings'); return; }
            if ($action==='flush_cache') { $count=CacheStore::clear(); $_SESSION['settings_msg']=['type'=>'success','msg'=>"Caché limpiada: $count archivos."]; header('Location: /admin/settings'); return; }
            if ($action==='export_db') { DatabaseBackup::download(); return; }
            if ($action==='restore') {
                $this->verifyPassword();
                if (($_POST['restore_confirmation']??'')!=='RESTAURAR') throw new \InvalidArgumentException('Escribe RESTAURAR para confirmar la sustitución de datos.');
                $file=$_FILES['import_sql_file']??[];
                if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Selecciona un respaldo SQL recibido correctamente.');
                \App\Core\SqlBackup::restore(Database::getInstance()->getConnection(),$file['tmp_name']); Settings::reset(); Auth::logout(); header('Location: /login'); return;
            }
            if (!in_array($action,['save','probe_smtp','save_publication'],true)) throw new \InvalidArgumentException('Acción de configuración desconocida.');
            $input=$_POST;
            if ($action==='save_publication') {
                $input=array_intersect_key($input,array_flip(['maintenance_mode','enable_security_headers','maintenance_message']));
                $input+=['maintenance_mode'=>'0','enable_security_headers'=>'0'];
            }
            $values=SiteConfiguration::validate($input); if (!\App\Core\IdentityAccess::superuser() && (array_key_exists('header_scripts',$values)||array_key_exists('footer_scripts',$values))) { http_response_code(403); exit('Publicar scripts requiere un administrador completo.'); }
            foreach (['header_scripts','footer_scripts'] as $key) if (isset($values[$key]) && $values[$key]!==Settings::get($key)) { $this->verifyPassword(); break; }
            Settings::writeMany($values); CacheStore::clear();
            $message='Configuración guardada. Los cambios ya están aplicados.'; $type='success';
            if ($action==='save_publication') {
                $message='Publicación y cabeceras guardadas. Mantenimiento público '
                    .($values['maintenance_mode']==='1'?'activado. Los visitantes reciben el aviso HTTP 503.':'desactivado.');
            }
            if ($action==='probe_smtp') {
                if (!\App\Core\RateLimiter::consume('smtp-probe:'.Auth::id(),3,300)) { $message='Configuración guardada. Espera unos minutos antes de repetir la comprobación SMTP.'; $type='warning'; }
                else { try { MailTransport::probe(); $message='Conexión SMTP y TLS verificadas. No se envió ningún correo ni se comprobó la contraseña del proveedor.'; } catch (\Throwable $e) { $message='Configuración guardada. No se completó la conexión SMTP/TLS; revisa el servidor, puerto, DNS y certificado.'; $type='warning'; } }
            }
            $publication=$action==='save_publication';
            $_SESSION['settings_msg']=['type'=>$type,'msg'=>$message,'section'=>$publication?'seguridad':''];
            header('Location: '.AdminPath::url('/admin/settings').($publication?'#seguridad':''),true,$publication?303:302);
        } catch (\InvalidArgumentException $e) { http_response_code(422); unset($_GET['action']); $this->index(['type'=>'danger','msg'=>$e->getMessage(),'section'=>$action==='save_publication'?'seguridad':'']); }
        catch (\Throwable $e) { error_log('Settings operation: '.$e->getMessage()); http_response_code(500); unset($_GET['action']); $this->index(['type'=>'danger','msg'=>'No se pudo completar la operación. Revisa el registro del servidor.','section'=>$action==='save_publication'?'seguridad':'']); }
    }
    private function verifyPassword(): void
    {
        $password=$_POST['current_password']??'';
        $user=Database::getInstance()->fetch('SELECT password FROM users WHERE id = ?',[Auth::id()]);
        if (!is_string($password) || strlen($password)>72 || !$user || !password_verify($password,$user['password'])) throw new \InvalidArgumentException('Confirma tu contraseña actual para esta operación.');
    }
}
