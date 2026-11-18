<?php
namespace App\Core;
use Firebase\JWT\{JWT,JWK};
final class EnterpriseOidc
{
    private array $claims=[];
    public function __construct(private string $issuer,private string $client,private string $secret) {}
    public static function url(string $url): array { $p=parse_url($url); if (!$p||($p['scheme']??'')!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||(isset($p['port'])&&$p['port']!==443)||filter_var($p['host'],FILTER_VALIDATE_IP)||!filter_var($p['host'],FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) throw new \InvalidArgumentException('Usa una URL HTTPS de un proveedor público.'); return $p; }
    public static function fetch(string $url,?string $post=null,array $headers=[]): array {
        $p=self::url($url); $addresses=[]; foreach (dns_get_record($p['host'],DNS_A|DNS_AAAA)?:[] as $record) { $ip=$record['ip']??$record['ipv6']??''; if (!$ip) continue; if (!SafeHttp::isPublicIp($ip)) throw new \RuntimeException('Proveedor no público.'); $addresses[]=$ip; } if (!$addresses) throw new \RuntimeException('DNS del proveedor no disponible.');
        $body=''; $curl=curl_init($url); $ip=$addresses[0]; curl_setopt_array($curl,[CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_RESOLVE=>[$p['host'].':443:'.(str_contains($ip,':')?'['.$ip.']':$ip)],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use (&$body) { if (strlen($body)+strlen($chunk)>2097152) return 0; $body.=$chunk; return strlen($chunk); }]);
        if ($post!==null) { curl_setopt($curl,CURLOPT_POST,true); curl_setopt($curl,CURLOPT_POSTFIELDS,$post); $headers[]='Content-Type: application/x-www-form-urlencoded'; } if ($headers) curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
        try { $ok=curl_exec($curl); if (!$ok||curl_getinfo($curl,CURLINFO_HTTP_CODE)!==200) throw new \RuntimeException('El proveedor no respondió correctamente.'); $json=json_decode($body,true,32,JSON_THROW_ON_ERROR); if (!is_array($json)) throw new \RuntimeException('Respuesta OIDC inválida.'); return $json; } finally { curl_close($curl); }
    }
    public static function validateClaims(array $claims,string $issuer,string $client,string $nonce): bool {
        $aud=$claims['aud']??null; if (!is_string($aud)&&!is_array($aud)) return false; $aud=is_array($aud)?$aud:[$aud];
        return ($claims['iss']??'')===$issuer && in_array($client,$aud,true) && (count($aud)===1||($claims['azp']??'')===$client) && is_string($claims['sub']??null) && $claims['sub']!=='' && strlen($claims['sub'])<=255 && is_string($claims['nonce']??null) && hash_equals($nonce,$claims['nonce']) && is_int($claims['exp']??null) && $claims['exp']>time() && is_int($claims['iat']??null) && $claims['iat']<=time()+60 && $claims['iat']>=time()-3600;
    }
    public function authenticate(): bool {
        $callback=rtrim(Environment::get('APP_URL'),'/').'/login/enterprise'; $stamp=hash('sha256',$this->issuer.'|'.$this->client.'|'.$this->secret); $metadata=self::fetch($this->issuer.'/.well-known/openid-configuration');
        if (($metadata['issuer']??'')!==$this->issuer) throw new \RuntimeException('El emisor no coincide.'); foreach (['authorization_endpoint','token_endpoint','jwks_uri'] as $endpoint) self::url($metadata[$endpoint]??'');
        if (!isset($_GET['code'])) { $state=bin2hex(random_bytes(32)); $nonce=bin2hex(random_bytes(32)); $verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'='); $_SESSION['goldensite_oidc']=['state'=>$state,'nonce'=>$nonce,'verifier'=>$verifier,'stamp'=>$stamp,'at'=>time()]; $query=['response_type'=>'code','scope'=>'openid profile email','client_id'=>$this->client,'redirect_uri'=>$callback,'state'=>$state,'nonce'=>$nonce,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256']; header('Location: '.$metadata['authorization_endpoint'].(str_contains($metadata['authorization_endpoint'],'?')?'&':'?').http_build_query($query,'','&',PHP_QUERY_RFC3986)); exit; }
        $pending=$_SESSION['goldensite_oidc']??null; unset($_SESSION['goldensite_oidc']); $state=$_GET['state']??null; $code=$_GET['code'];
        if (!$pending||time()-$pending['at']>300||!is_string($state)||!hash_equals($pending['state'],$state)||!hash_equals($pending['stamp'],$stamp)||!is_string($code)||strlen($code)>4000) throw new \RuntimeException('Respuesta OIDC vencida o no solicitada.');
        $headers=[]; $post=['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$callback,'code_verifier'=>$pending['verifier']]; $methods=$metadata['token_endpoint_auth_methods_supported']??['client_secret_basic'];
        if ($this->secret!==''&&in_array('client_secret_basic',$methods,true)) $headers[]='Authorization: Basic '.base64_encode(rawurlencode($this->client).':'.rawurlencode($this->secret)); else { $post['client_id']=$this->client; if ($this->secret!=='') { if (!in_array('client_secret_post',$methods,true)) throw new \RuntimeException('Método de autenticación no compatible.'); $post['client_secret']=$this->secret; } }
        $tokens=self::fetch($metadata['token_endpoint'],http_build_query($post,'','&',PHP_QUERY_RFC3986),$headers); $token=$tokens['id_token']??null; if (!is_string($token)||strlen($token)>32768) throw new \RuntimeException('Token de identidad inválido.');
        $jwks=self::fetch($metadata['jwks_uri']); $jwks['keys']=array_values(array_filter($jwks['keys']??[],static fn($key)=>is_array($key)&&in_array($key['kty']??'',['RSA','EC'],true)&&in_array($key['alg']??'RS256',['RS256','ES256'],true))); $keys=JWK::parseKeySet($jwks,'RS256'); $claims=(array)JWT::decode($token,$keys);
        if (!self::validateClaims($claims,$this->issuer,$this->client,$pending['nonce'])) throw new \RuntimeException('El token no pertenece a esta solicitud.'); $this->claims=$claims; return true;
    }
    public function getVerifiedClaims(string $key): mixed { return $this->claims[$key]??null; }
}
