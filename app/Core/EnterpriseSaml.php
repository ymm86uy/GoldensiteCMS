<?php
namespace App\Core;

use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

final class EnterpriseSaml
{
    public static function base(): string
    {
        $base = rtrim(Environment::get('APP_URL'),'/');
        $url = parse_url($base);
        if (!$url || !filter_var($base,FILTER_VALIDATE_URL) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])
            || (($url['scheme']??'')!=='https' && !preg_match('~^http://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?$~D',$base))) {
            throw new \InvalidArgumentException('Configura APP_URL como origen HTTPS de Goldensite.');
        }
        return $base;
    }

    public static function transport(): void
    {
        if (str_starts_with(self::base(),'https://')) {
            $https = strtolower((string)($_SERVER['HTTPS']??''));
            if (!in_array($https,['on','1'],true)) throw new \RuntimeException('El servidor debe identificar esta solicitud como HTTPS.');
        } elseif (!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) {
            throw new \RuntimeException('HTTP se reserva para pruebas locales.');
        }
    }

    public static function config(): array
    {
        return json_decode(Settings::get('enterprise_saml_config','{}'),true,8,JSON_THROW_ON_ERROR);
    }

    public static function issuer(array $config): string
    {
        if (empty($config['issuer'])) throw new \InvalidArgumentException('Configura el identificador del proveedor SAML.');
        return hash('sha256',$config['issuer']);
    }

    public static function toolkit(array $config): SamlAuth
    {
        self::issuer($config);
        $base = self::base();
        Utils::setBaseURL($base.'/');
        return new SamlAuth([
            'strict'=>true,
            'debug'=>false,
            'baseurl'=>$base.'/',
            'sp'=>[
                'entityId'=>$base.'/login/saml/metadata',
                'assertionConsumerService'=>['url'=>$base.'/login/saml/acs'],
                'NameIDFormat'=>Constants::NAMEID_UNSPECIFIED,
            ],
            'idp'=>[
                'entityId'=>$config['issuer'],
                'singleSignOnService'=>['url'=>$config['url']],
                'x509cert'=>$config['cert'],
            ],
            'security'=>[
                'wantMessagesSigned'=>true,
                'wantAssertionsSigned'=>true,
                'wantNameId'=>true,
                'wantAttributeStatement'=>false,
                'destinationStrictlyMatches'=>true,
                'rejectUnsolicitedResponsesWithInResponseTo'=>true,
                'rejectDeprecatedAlgorithm'=>true,
                'requestedAuthnContext'=>false,
                'signatureAlgorithm'=>XMLSecurityKey::RSA_SHA256,
                'digestAlgorithm'=>XMLSecurityDSig::SHA256,
            ],
        ]);
    }

    public static function ready(): bool
    {
        return FrontendEngine::isModuleActive('sso') && (self::config()['enabled']??false)===true;
    }

    public static function ordinary(array $user): bool
    {
        return ($user['role']??'')==='user' && (int)($user['role_id']??0)===2 && !IdentityAccess::role((int)$user['id']);
    }
}
