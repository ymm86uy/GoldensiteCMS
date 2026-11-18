<?php
namespace App\Controllers;
use App\Core\{ConsoleView,Csrf,Database,FrontendEngine,RateLimiter,RequestGuard,ThemeService};
final class ContactController
{
    public function index(): void {
        $siteSettings=FrontendEngine::getSettings(); $activeTheme=ThemeService::active(); $siteTitle='Contacto · '.($siteSettings['site_title']??'GoldenSiteCMS'); $status=$_SESSION['contact_notice']??null; unset($_SESSION['contact_notice']); $values=['name'=>'','email'=>'','subject'=>'','message'=>''];
        $inquiryTypes=['licenses'=>'Comprar módulos o licencias','onboarding'=>'Empezar con GoldenSiteCMS','support'=>'Soporte técnico','project'=>'Proyecto o integración','general'=>'Otra consulta']; $moduleId=is_string($_GET['module']??null)?$_GET['module']:''; if (!\App\Core\ModuleCatalog::get($moduleId)) $moduleId=''; $kind=$moduleId!==''?'licenses':'onboarding';
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); if (!RateLimiter::consume('contact:'.($_SERVER['REMOTE_ADDR']??''),5,300)) { http_response_code(429); $status=['type'=>'danger','msg'=>'Has enviado varios mensajes. Espera unos minutos antes de volver a intentarlo.']; }
            else try { foreach (['name'=>150,'email'=>150,'subject'=>200,'message'=>20000] as $key=>$max) $values[$key]=ConsoleView::field($_POST,$key,$max); if (!$values['name']||!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||!$values['subject']||!$values['message']||($_POST['website_confirm']??'')!=='') throw new \InvalidArgumentException('Completa todos los campos con datos válidos.');
                $kind=ConsoleView::field($_POST,'inquiry_type',30)?:'general'; $moduleId=ConsoleView::field($_POST,'module_id',100); if (!isset($inquiryTypes[$kind])||($moduleId!==''&&!\App\Core\ModuleCatalog::get($moduleId))) throw new \InvalidArgumentException('Selecciona un motivo y módulo válidos.');
                if (($_POST['contact_consent']??'')!=='1') throw new \InvalidArgumentException('Confirma el uso de tus datos para responder a esta consulta.');
                $nonce=ConsoleView::field($_POST,'contact_nonce',32); if (empty($_SESSION['contact_nonces'][$nonce])) throw new \InvalidArgumentException('El formulario venció o ya fue enviado. Recarga para continuar.');
                Database::getInstance()->execute('INSERT INTO goldensite_messages (name,email,subject,message,inquiry_type,module_id) VALUES (?,?,?,?,?,?)',[...array_values($values),$kind,$moduleId?:null]); unset($_SESSION['contact_nonces'][$nonce]); $id=(int)Database::getInstance()->getConnection()->lastInsertId(); \App\Controllers\Admin\WebhooksController::dispatch('contact_received','Nueva consulta recibida. Referencia NX-'.str_pad((string)$id,6,'0',STR_PAD_LEFT).'.');
                try { \App\Core\AutomationEngine::trigger('contact_received',array_intersect_key($values,array_flip(['name','email','subject']))); } catch (\Throwable $e) { error_log('Contact saved; optional automation could not be completed.'); }
                $_SESSION['contact_notice']=['type'=>'success','msg'=>'Mensaje recibido. Tu referencia es NX-'.str_pad((string)$id,6,'0',STR_PAD_LEFT).'.']; header('Location: /contacto',true,303); return;
            } catch (\InvalidArgumentException $e) { http_response_code(422); $status=['type'=>'danger','msg'=>$e->getMessage()]; } catch (\Throwable $e) { error_log('Contact message could not be persisted.'); http_response_code(500); $status=['type'=>'danger','msg'=>'No pudimos guardar tu mensaje. Inténtalo de nuevo más tarde.']; }
        }
        $csrfToken=Csrf::generate(); $contactNonce=bin2hex(random_bytes(16)); $_SESSION['contact_nonces'][$contactNonce]=time(); if (count($_SESSION['contact_nonces'])>20) array_shift($_SESSION['contact_nonces']); $e=ConsoleView::escape(...); ob_start(); require dirname(__DIR__,2).'/views/frontend/contact.php'; $content=ob_get_clean(); $layout=dirname(__DIR__,2).'/views/themes/'.$activeTheme.'/layout.php'; require is_file($layout)?$layout:dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
