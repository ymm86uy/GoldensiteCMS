<?php
namespace App\Controllers\Admin;

use App\Core\{AdminPath,Auth,ConsoleView,EnterpriseSaml,FrontendEngine,RateLimiter,RequestGuard,Settings};

final class SsoController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD']==='POST') { $this->save(); return; }
        header('Cache-Control: private, no-store');
        $config = EnterpriseSaml::config();
        $enabled = FrontendEngine::isModuleActive('sso');
        $scim = Settings::get('enterprise_scim_enabled')==='1';
        $token = $_SESSION['enterprise_new_token']??'';
        $notice = $_SESSION['enterprise_notice']??'';
        unset($_SESSION['enterprise_new_token'],$_SESSION['enterprise_notice']);
        try { $origin = EnterpriseSaml::base(); } catch (\InvalidArgumentException) { $origin = ''; }
        $base = AdminPath::url('/admin/sso');
        ConsoleView::render('enterprise_sso',compact('config','enabled','scim','token','notice','origin','base'),'Goldensite SSO & SCIM Enterprise');
    }

    public function save(): void
    {
        Auth::requireAdmin();
        RequestGuard::post();
        try {
            if (!FrontendEngine::isModuleActive('sso')) throw new \InvalidArgumentException('Activa el módulo desde Módulos y extensiones.');
            if (!RateLimiter::consume('enterprise-settings:'.Auth::id(),10,300)) throw new \InvalidArgumentException('Espera antes de volver a intentar.');
            ConsoleView::password();
            EnterpriseSaml::transport();
            $action = ConsoleView::field($_POST,'action',30);
            $config = EnterpriseSaml::config();
            if ($action==='saml') {
                $enabled = ($_POST['enabled']??'')==='1';
                $issuer = ConsoleView::field($_POST,'issuer',512);
                $url = ConsoleView::field($_POST,'url',1024);
                $cert = $_POST['cert']??'';
                if (!is_string($cert) || strlen($cert)>12000) throw new \InvalidArgumentException('Certificado inválido.');
                $cert = trim($cert);
                if ($issuer!=='' || $url!=='' || $cert!=='' || $enabled) {
                    $endpoint = parse_url($url);
                    if ($issuer==='' || !filter_var($url,FILTER_VALIDATE_URL) || !$endpoint || isset($endpoint['user']) || isset($endpoint['pass']) || isset($endpoint['fragment'])
                        || (($endpoint['scheme']??'')!=='https' && !preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::[0-9]+)?/~D',$url))) {
                        throw new \InvalidArgumentException('Introduce el identificador y una URL HTTPS de inicio de sesión del proveedor.');
                    }
                    $parsed = openssl_x509_parse($cert);
                    if (!$parsed || ($parsed['validFrom_time_t']??PHP_INT_MAX)>time() || ($parsed['validTo_time_t']??0)<=time()) {
                        throw new \InvalidArgumentException('Introduce el certificado X.509 vigente del proveedor en formato PEM.');
                    }
                    $next = ['issuer'=>$issuer,'url'=>$url,'cert'=>$cert,'enabled'=>$enabled];
                    EnterpriseSaml::toolkit($next);
                } else {
                    $next = ['enabled'=>false];
                }
                $values = ['enterprise_saml_config'=>json_encode($next,JSON_THROW_ON_ERROR)];
                if (($config['issuer']??'')!==($next['issuer']??'')) {
                    $values['enterprise_scim_enabled'] = '0';
                    $values['enterprise_scim_hash'] = '';
                }
                Settings::writeMany($values);
                $_SESSION['enterprise_notice'] = 'Configuración SAML guardada.';
            } elseif ($action==='scim_rotate') {
                $issuer = EnterpriseSaml::issuer($config);
                $token = bin2hex(random_bytes(32));
                Settings::writeMany(['enterprise_scim_enabled'=>'1','enterprise_scim_hash'=>hash('sha256',$issuer."\0".$token)]);
                $_SESSION['enterprise_new_token'] = $token;
                $_SESSION['enterprise_notice'] = 'Copia el nuevo token. Se muestra una vez y reemplaza al anterior.';
            } elseif ($action==='scim_disable') {
                Settings::writeMany(['enterprise_scim_enabled'=>'0','enterprise_scim_hash'=>'']);
                $_SESSION['enterprise_notice'] = 'Aprovisionamiento desactivado y token revocado.';
            } else {
                throw new \InvalidArgumentException('Operación no disponible.');
            }
            ConsoleView::audit('enterprise_identity_configuration',$action);
        } catch (\InvalidArgumentException $error) {
            $_SESSION['enterprise_notice'] = $error->getMessage();
        } catch (\Throwable $error) {
            $_SESSION['enterprise_notice'] = 'No se pudo guardar. Revisa el certificado, el proveedor y la configuración HTTPS.';
        }
        header('Location: '.AdminPath::url('/admin/sso'),true,303);
    }
}
