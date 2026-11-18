<?php
namespace App\Controllers;
use App\Core\{Auth,ConsoleView,Database,EnterpriseOidc,Environment,IdentityAccess,RateLimiter,SecretStore,Settings};
final class EnterpriseLoginController
{
    public function login(): void {
        if (Settings::get('oidc_enabled','0')!=='1') { http_response_code(404); exit('Acceso empresarial no configurado.'); }
        if (!RateLimiter::consume('oidc:'.($_SERVER['REMOTE_ADDR']??''),20,600)) { http_response_code(429); exit('Espera antes de volver a intentarlo.'); }
        try {
            $base=rtrim(Environment::get('APP_URL'),'/'); if (!str_starts_with($base,'https://')&&!preg_match('~^http://(?:localhost|127\.0\.0\.1)(?::\d+)?$~D',$base)) throw new \RuntimeException('Configura APP_URL con HTTPS antes de utilizar el acceso empresarial.');
            $issuer=Settings::get('oidc_issuer'); $oidc=new EnterpriseOidc($issuer,Settings::get('oidc_client_id'),SecretStore::open(Settings::get('oidc_secret')));
            if (!isset($_GET['code'])) $_SESSION['oidc_started']=time(); elseif (time()-(int)($_SESSION['oidc_started']??0)>300) throw new \RuntimeException('El acceso empresarial venció.');
            if (!$oidc->authenticate()) throw new \RuntimeException('El proveedor no confirmó la identidad.'); $sub=$oidc->getVerifiedClaims('sub'); if (!is_string($sub)||$sub==='') throw new \RuntimeException('Identidad inválida.');
            $user=Database::getInstance()->fetch('SELECT u.* FROM users u JOIN goldensite_identity_links l ON l.user_id=u.id WHERE l.issuer=? AND l.subject_hash=?',[$issuer,hash('sha256',$sub)]);
            if (!$user||!in_array($user['status'],['active','1','enabled'],true)||IdentityAccess::superuser($user)) throw new \RuntimeException('La identidad no está vinculada a una cuenta activa autorizada.');
            if (!empty($user['totp_secret'])) { Auth::beginSecondFactor($user); header('Location: /login/2fa'); return; }
            $amr=$oidc->getVerifiedClaims('amr'); Auth::completeLogin($user); $_SESSION['mfa_verified']=is_array($amr)&&in_array('mfa',$amr,true); ConsoleView::audit('enterprise_login'); header('Location: '.(IdentityAccess::canEnter()?'/admin':'/mi-cuenta'));
        } catch (\Throwable $e) { unset($_SESSION['goldensite_oidc'],$_SESSION['oidc_started'],$_SESSION['openid_connect_nonce'],$_SESSION['openid_connect_state'],$_SESSION['openid_connect_code_verifier']); error_log('Enterprise login rejected; verify provider configuration and linked identity.'); http_response_code(403); echo 'No se pudo verificar el acceso empresarial. Revisa la configuración y la vinculación con administración.'; }
    }
}
