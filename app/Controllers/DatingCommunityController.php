<?php
namespace App\Controllers;
use App\Core\{Auth,ConsoleView,Csrf,Database,DatingMedia,DatingService,FrontendEngine,RateLimiter,RequestGuard};

final class DatingCommunityController
{
    private function available(): bool
    {
        header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
        if (FrontendEngine::isModuleActive('dating')) return true;
        http_response_code(403); $siteTitle='Goldensite Love Dating'; $content='<link rel="stylesheet" href="/assets/dating/community.css?v=20261104r1"><section class="nl-shell"><div class="nl-heading"><h1>Goldensite Love Dating</h1></div><div class="nl-panel"><p>La comunidad no está disponible en este sitio.</p><a href="/">Volver al sitio</a></div></section>'; require dirname(__DIR__,2).'/views/layouts/main.php'; return false;
    }
    private static function id(string $key): int
    {
        $id=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT); if ($id===false||$id===null||$id<1) throw new \InvalidArgumentException('Identificador inválido.'); return $id;
    }
    public function index(): void
    {
        if (!$this->available()) return;
        $tabs=['discover','profile','matches','stories','safety','privacy','credits']; $tab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],$tabs,true)?$_GET['tab']:'discover';
        $notice=$_SESSION['dating_notice']??null; unset($_SESSION['dating_notice']); $error=null; $me=DatingService::mine();
        $chat=filter_var($_GET['chat']??0,FILTER_VALIDATE_INT)?:0;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            Auth::requireAuth(); RequestGuard::post();
            try {
                if (!RateLimiter::consume('dating-action:'.Auth::id(),60,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.');
                $ticket=ConsoleView::field($_POST,'action_token',32); $stored=$_SESSION['dating_actions'][$ticket]??null;
                if (!$stored||$stored['user']!==(int)Auth::id()||$stored['until']<time()) throw new \InvalidArgumentException('El formulario venció o ya se procesó. Recarga la página.');
                unset($_SESSION['dating_actions'][$ticket]);
                $action=ConsoleView::field($_POST,'action',32);
                if ($action==='onboard') { DatingService::onboard($_POST); $tab='profile'; $notice='Perfil creado. Confirma tu correo y añade una foto para la revisión de moderación.'; }
                elseif ($action==='profile') { DatingService::update($_POST); $tab='profile'; $notice='Cambios guardados para revisión. Tus controles de privacidad se aplican inmediatamente.'; }
                elseif (!$me||$me['account_status']==='deleted') throw new \InvalidArgumentException('Crea tu perfil antes de usar esta función.');
                elseif ($action==='upload_photo') { DatingMedia::upload(is_array($_FILES['photo']??null)?$_FILES['photo']:[],(int)$me['id']); $tab='profile'; $notice='Foto procesada y guardada de forma privada. Está pendiente de moderación.'; }
                elseif ($action==='avatar') { DatingService::avatar(ConsoleView::field($_POST,'photo_id',32)); $tab='profile'; $notice='Foto principal actualizada.'; }
                elseif ($action==='remove_photo') { DatingMedia::remove(ConsoleView::field($_POST,'photo_id',32),(int)$me['id']); $tab='profile'; $notice='Foto retirada.'; }
                elseif ($action==='like'||$action==='skip') { $mutual=DatingService::like(self::id('target_id'),$action==='skip'); $notice=$mutual?'Hay una coincidencia mutua. Ya pueden conversar.':($action==='skip'?'Perfil omitido.':'Interés guardado. La otra persona debe corresponder para abrir una conversación.'); $tab=$mutual?'matches':'discover'; }
                elseif (in_array($action,['block','unblock','unmatch'],true)) { DatingService::relationship(self::id('target_id'),$action); $notice=$action==='block'?'Perfil bloqueado en ambos sentidos.':($action==='unblock'?'Bloqueo retirado. Las coincidencias no se reabren automáticamente.':'Coincidencia cerrada.'); $tab=$action==='unblock'?'privacy':'matches'; $chat=0; }
                elseif ($action==='report') { DatingService::report(self::id('target_id'),$_POST); $notice='Denuncia enviada a moderación. No se comparte con la persona denunciada.'; $tab='safety'; $chat=0; }
                elseif ($action==='pause'||$action==='resume') { DatingService::pause($action==='pause'); $notice=$action==='pause'?'Tu perfil queda oculto en descubrimiento. Las conversaciones existentes continúan.':'Descubrimiento reactivado según la revisión de tu perfil.'; $tab='privacy'; }
                elseif ($action==='verify_email') { DatingService::requestVerification(); $notice='Solicitud de confirmación registrada. El correo lo procesa el servicio del sitio.'; $tab='profile'; }
                elseif ($action==='message') { $chat=self::id('match_id'); DatingService::send($chat,$_POST); $notice='Mensaje enviado.'; $tab='matches'; }
                elseif ($action==='story') { DatingService::story($_POST); $notice='Historia publicada durante 24 horas.'; $tab='stories'; }
                elseif ($action==='remove_story') { DatingService::removeStory(self::id('story_id')); $notice='Historia retirada.'; $tab='stories'; }
                elseif ($action==='gift') { $chat=self::id('match_id'); DatingService::gift($chat,self::id('gift_id')); $notice='Regalo enviado con cargo a tus créditos virtuales.'; $tab='matches'; }
                elseif ($action==='export'||$action==='close') {
                    if (!RateLimiter::consume('dating-private:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.'); ConsoleView::password();
                    if ($action==='export') { header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-dating-actividad.json"'); echo json_encode(DatingService::export(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); return; }
                    if (($_POST['confirm_close']??'')!=='1') throw new \InvalidArgumentException('Confirma que deseas cerrar el perfil de Dating.'); DatingService::close(); $notice='Perfil de Dating cerrado. Tu cuenta de GoldenSiteCMS se conserva.'; $tab='profile';
                } else throw new \InvalidArgumentException('Acción no disponible. Recarga la página para usar el formulario actualizado.');
                $_SESSION['dating_notice']=$notice; header('Location: /dating?tab='.$tab.($chat>0&&$tab==='matches'?'&chat='.$chat:''),true,303); return;
            } catch (\InvalidArgumentException $e) { http_response_code(422); $error=$e->getMessage(); }
        }
        $me=DatingService::mine(); $matches=$me?DatingService::matches():[]; $selected=$chat>0?DatingService::match($chat):null;
        if ($chat>0&&!$selected) { http_response_code(403); $error='Esta conversación no está disponible para tu cuenta.'; }
        $messages=$selected?DatingService::messages($chat):[]; $photos=$me?DatingService::photos():[]; $feed=['profiles'=>[],'total'=>0,'page'=>1,'pages'=>0];
        if ($tab==='discover'&&$me) { try { $feed=DatingService::discover($_GET); } catch (\InvalidArgumentException $e) { http_response_code(422); $error=$e->getMessage(); } }
        $stories=$tab==='stories'?DatingService::stories():[]; $db=Database::getInstance();
        $blocks=$me?$db->fetchAll('SELECT b.target_id,p.username FROM goldensite_dating_privacy_blocks b JOIN goldensite_dating_profiles p ON p.id=b.target_id WHERE b.blocker_id=?',[$me['id']]):[];
        $gifts=$db->fetchAll('SELECT id,name,price,icon FROM goldensite_dating_gifts WHERE status=1 ORDER BY price LIMIT 30');
        $transactions=$me?$db->fetchAll('SELECT type,amount,description,created_at FROM goldensite_dating_transactions WHERE user_id=? ORDER BY id DESC LIMIT 50',[$me['id']]):[];
        if ($me) $db->execute('UPDATE goldensite_dating_profiles SET last_seen=NOW() WHERE id=?',[$me['id']]);
        $token=bin2hex(random_bytes(16)); $_SESSION['dating_actions']=array_filter($_SESSION['dating_actions']??[],static fn($v)=>is_array($v)&&($v['until']??0)>=time()); $_SESSION['dating_actions']=array_slice($_SESSION['dating_actions'],-99,null,true); $_SESSION['dating_actions'][$token]=['user'=>(int)(Auth::id()??0),'until'=>time()+1800];
        $e=ConsoleView::escape(...); $csrf=Csrf::generate(); $siteTitle='Goldensite Love Dating · Conexiones con intención';
        ob_start(); require dirname(__DIR__,2).'/views/dating/community.php'; $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
    public function messages(string $id): void
    {
        header('Cache-Control: no-store, private'); header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()||!FrontendEngine::isModuleActive('dating')) { http_response_code(403); echo '{"success":false}'; return; }
        try { if (!RateLimiter::consume('dating-poll:'.Auth::id(),60,60)) { http_response_code(429); echo '{"success":false}'; return; } $after=filter_var($_GET['after']??0,FILTER_VALIDATE_INT); if ($after===false||$after<0||!ctype_digit($id)) throw new \InvalidArgumentException('Consulta inválida.'); $messages=DatingService::messages((int)$id,$after); echo json_encode(['success'=>true,'messages'=>$messages],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
        catch (\InvalidArgumentException $e) { http_response_code(403); echo '{"success":false}'; }
    }
    public function media(string $id): void
    {
        header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
        if ((!FrontendEngine::isModuleActive('dating')&&!(($_GET['review']??'')==='1'&&\App\Core\IdentityAccess::superuser()&&\App\Core\LicenseEngine::isLicenseActive('dating')))||!DatingMedia::allowed($id,($_GET['review']??'')==='1')) { http_response_code(404); return; }
        $path=DatingMedia::path($id); if (!is_file($path)) { http_response_code(404); return; }
        while (ob_get_level()>0) ob_end_clean(); if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: image/jpeg'); header('Content-Length: '.filesize($path)); readfile($path);
    }
    public function confirm(): void
    {
        if (!$this->available()) return; header('Referrer-Policy: no-referrer');
        $e=ConsoleView::escape(...); $done=false; $error=null;
        try {
            if (!RateLimiter::consume('dating-confirm-link:'.($_SERVER['REMOTE_ADDR']??''),30,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.');
            $input=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET; $id=filter_var($input['user']??null,FILTER_VALIDATE_INT)?:0; $token=ConsoleView::field($input,'token',64);
            if (!DatingService::confirmation($id,$token)) throw new \InvalidArgumentException('El enlace no es válido o ha vencido.');
            if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); $done=DatingService::confirmation($id,$token,true); }
        } catch (\InvalidArgumentException $e2) { http_response_code(422); $error=$e2->getMessage(); }
        $csrf=Csrf::generate(); $siteTitle='Confirma tu correo · Goldensite Love Dating'; ob_start(); require dirname(__DIR__,2).'/views/dating/confirm.php'; $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
