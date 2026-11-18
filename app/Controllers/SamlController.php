<?php
namespace App\Controllers;

use App\Core\{Auth,ConsoleView,Database,EnterpriseSaml,RateLimiter};

final class SamlController
{
    private const COOKIE = 'goldensite_saml_flow';

    public function start(): void
    {
        $this->handle(function (): void {
            $config = EnterpriseSaml::config();
            $auth = EnterpriseSaml::toolkit($config);
            $relay = bin2hex(random_bytes(24));
            $browser = bin2hex(random_bytes(24));
            $url = $auth->login($relay,[],false,false,true);
            $db = Database::getInstance();
            $db->execute('DELETE FROM goldensite_saml_requests WHERE expires_at<=?',[time()]);
            $db->execute('INSERT INTO goldensite_saml_requests VALUES (?,?,?,?,?)',[
                hash('sha256',$relay),hash('sha256',$browser),$auth->getLastRequestID(),self::fingerprint($config),time()+300,
            ]);
            setcookie(self::COOKIE,$browser,$this->cookieOptions(time()+300));
            header('Location: '.$url);
        });
    }

    public function metadata(): void
    {
        $this->handle(function (): void {
            $settings = EnterpriseSaml::toolkit(EnterpriseSaml::config())->getSettings();
            $xml = $settings->getSPMetadata();
            if ($settings->validateMetadata($xml)) throw new \RuntimeException('Metadatos SAML inválidos.');
            header('Content-Type: application/samlmetadata+xml; charset=utf-8');
            echo $xml;
        });
    }

    public function acs(): void
    {
        $this->handle(function (): void {
            $relay = $_POST['RelayState']??'';
            $browser = $_COOKIE[self::COOKIE]??'';
            $response = $_POST['SAMLResponse']??'';
            if (!is_string($relay) || !preg_match('/^[a-f0-9]{48}$/D',$relay)
                || !is_string($browser) || !preg_match('/^[a-f0-9]{48}$/D',$browser)
                || !is_string($response) || strlen($response)>262144) {
                throw new \InvalidArgumentException('Flujo SAML inválido.');
            }
            $config = EnterpriseSaml::config();
            $db = Database::getInstance();
            $hash = hash('sha256',$relay);
            $flow = $db->fetch('SELECT * FROM goldensite_saml_requests WHERE relay_hash=?',[$hash]);
            if (!$flow || (int)$flow['expires_at']<=time() || !hash_equals($flow['browser_hash'],hash('sha256',$browser))
                || !hash_equals($flow['config_hash'],self::fingerprint($config))) {
                throw new \InvalidArgumentException('Flujo SAML vencido o ajeno a este navegador.');
            }
            $consume = $db->getConnection()->prepare('DELETE FROM goldensite_saml_requests WHERE relay_hash=?');
            $consume->execute([$hash]);
            if ($consume->rowCount()!==1) throw new \InvalidArgumentException('Flujo SAML ya consumido.');
            setcookie(self::COOKIE,'',$this->cookieOptions(time()-3600));
            $auth = EnterpriseSaml::toolkit($config);
            $auth->processResponse($flow['request_id']);
            $assertion = $auth->getLastAssertionId();
            $expiry = (int)$auth->getLastAssertionNotOnOrAfter();
            $subject = $auth->isAuthenticated() ? $auth->getNameId() : null;
            if ($auth->getErrors() || !is_string($subject) || $subject==='' || !is_string($assertion) || $assertion==='' || $expiry<=time()) {
                throw new \InvalidArgumentException('La respuesta SAML no cumple la política de firma o vigencia.');
            }
            $issuer = EnterpriseSaml::issuer($config);
            $db->execute('DELETE FROM goldensite_saml_replays WHERE expires_at<=?',[time()]);
            $db->execute('INSERT INTO goldensite_saml_replays VALUES (?,?)',[hash('sha256',$issuer."\0".$assertion),$expiry+300]);
            $user = $db->fetch("SELECT u.* FROM users u JOIN goldensite_scim_users s ON s.user_id=u.id
                WHERE s.issuer_hash=? AND s.subject_hash=? AND s.deleted=0 AND u.status='active'",[$issuer,hash('sha256',$subject)]);
            if (!$user || !EnterpriseSaml::ordinary($user)) throw new \InvalidArgumentException('Cuenta no aprovisionada o no autorizada.');
            if (!empty($user['totp_secret'])) {
                Auth::beginSecondFactor($user);
                header('Location: /login/2fa',true,303);
                return;
            }
            Auth::completeLogin($user);
            ConsoleView::audit('enterprise_saml_login');
            header('Location: /mi-cuenta',true,303);
        });
    }

    private static function fingerprint(array $config): string
    {
        return hash('sha256',EnterpriseSaml::base().json_encode($config,JSON_THROW_ON_ERROR));
    }

    private function cookieOptions(int $expires): array
    {
        $secure = str_starts_with(EnterpriseSaml::base(),'https://');
        return ['expires'=>$expires,'path'=>'/login/saml','secure'=>$secure,'httponly'=>true,'samesite'=>$secure?'None':'Lax'];
    }

    private function handle(callable $action): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        if (!RateLimiter::consume('saml:'.($_SERVER['REMOTE_ADDR']??''),60,300)) {
            http_response_code(429);
            return;
        }
        try {
            if (!EnterpriseSaml::ready()) { http_response_code(404); return; }
            EnterpriseSaml::transport();
            $action();
        } catch (\Throwable $error) {
            error_log('Acceso SAML rechazado. Comprueba configuración, firma y cuenta aprovisionada.');
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No se pudo verificar el acceso SAML. Vuelve a iniciar el acceso o consulta con administración.';
        }
    }
}
