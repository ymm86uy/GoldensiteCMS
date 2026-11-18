<?php
namespace App\Security;
final class WafPolicy
{
    public const VERSION = 'NX-WAF-2026.11.04';
    public static function rules(): array
    {
        return [
            'NX-100'=>['name'=>'Ruta manipulada o ambigua','category'=>'Protocolo','score'=>10],
            'NX-110'=>['name'=>'Archivos privados o exploración de software','category'=>'Exposición','score'=>10],
            'NX-120'=>['name'=>'Método HTTP no admitido','category'=>'Protocolo','score'=>10],
            'NX-130'=>['name'=>'Límite de tamaño o complejidad','category'=>'Recursos','score'=>10],
            'NX-200'=>['name'=>'Patrón de inyección SQL','category'=>'SQLi','score'=>5],
            'NX-210'=>['name'=>'Marcado activo en un parámetro','category'=>'XSS','score'=>5],
            'NX-220'=>['name'=>'Inclusión de archivos o ejecución remota','category'=>'LFI/RCE','score'=>5],
            'NX-230'=>['name'=>'Entidad XML externa','category'=>'XXE','score'=>5],
            'NX-240'=>['name'=>'Inyección de cabeceras','category'=>'Protocolo','score'=>5],
            'NX-300'=>['name'=>'Exceso de solicitudes','category'=>'Frecuencia','score'=>10],
            'NX-400'=>['name'=>'Dirección en la lista de bloqueo','category'=>'Acceso','score'=>10],
            'NX-500'=>['name'=>'Archivo rechazado por ClamAV','category'=>'Archivos','score'=>10]
        ];
    }
    public static function decode(string $value): string
    {
        for ($i=0;$i<4;$i++) { $next=rawurldecode($value); if ($next===$value) break; $value=$next; }
        return html_entity_decode($value, ENT_QUOTES|ENT_HTML5|ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function inspect(string $method, string $uri, array $query, array $form, bool $author = false, int $length = 0): array
    {
        $hits=[]; $add=static function($id,$field) use (&$hits): void { $hits[$id]=['id'=>$id,'field'=>$field]+self::rules()[$id]; };
        $path = explode('?', $uri, 2)[0]; $decoded=self::decode($path);
        if (preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)|[\x00-\x1f\x7f]|\\\\~', $decoded)) $add('NX-100','path');
        if (preg_match('~(?:^|/)(?:\.(?!well-known(?:/|$))[^/]+|storage|config|vendor|database|node_modules)(?:/|$)|(?:wp-login|xmlrpc)\.php|phpunit|eval-stdin|server-status|\.(?:bak|sql|ini|log|lock)$~i', $decoded)) $add('NX-110','path');
        if (!in_array(strtoupper($method),['GET','POST','HEAD','OPTIONS'],true)
            && !\App\Controllers\ScimController::isWriteRoute($method,$path)) $add('NX-120','method');
        if (strlen($uri)>8192 || $length>25165824) $add('NX-130','request');
        $fields=0; $bytes=0;
        $walk=function(array $data,string $prefix,int $depth=0) use (&$walk,&$fields,&$bytes,$author,$decoded,$add): void {
            if ($depth>8) { $add('NX-130',$prefix); return; }
            foreach ($data as $key=>$value) {
                if (++$fields>500) { $add('NX-130',$prefix); return; }
                $field=$prefix.'.'.substr((string)$key,0,70);
                if (is_array($value)) { $walk($value,$field,$depth+1); continue; }
                if (!is_string($value)) continue;
                $bytes+=strlen($value);
                if ($bytes>2097152) { $add('NX-130',$field); return; }
                if (preg_match('/pass|secret|token|signature|license_key/i',(string)$key)) continue;
                if ($author && $prefix==='post' && in_array((string)$key,['header_scripts','footer_scripts'],true) && \App\Core\AdminPath::normalize($decoded)==='/admin/settings') continue;
                $textField=in_array((string)$key,['message','content','html_code','custom_css','config_json','import_sql','revocation_code','update_signature'],true);
                if ($prefix==='post' && $textField && ($author || in_array($decoded,['/chat','/api/chat/send','/contacto'],true))) continue;
                $v=self::decode(substr($value,0,131072));
                if (preg_match('~\bunion\s+(?:all\s+)?select\b|\b(?:or|and)\s+[\x27\x22]?([0-9]{1,8})[\x27\x22]?\s*=\s*[\x27\x22]?\1\b|\b(?:sleep|benchmark)\s*\(|;\s*(?:drop|alter|truncate)\s+(?:table|database)\b~i',$v)) $add('NX-200',$field);
                if (preg_match('~<\s*(?:script|iframe|object|embed)\b|\bon(?:error|load|mouseover)\s*=|javascript\s*:~i',$v)) $add('NX-210',$field);
                if (preg_match('~(?:php|data|expect|zip)://|(?:^|[/\\\\])\.\.[/\\\\]|/etc/passwd|\b(?:system|shell_exec|passthru|eval)\s*\(|(?:;|\|\||&&)\s*(?:curl|wget|bash|cmd|powershell|cat)\b~i',$v)) $add('NX-220',$field);
                if (preg_match('~<!\s*(?:DOCTYPE|ENTITY)\s+[^>]*(?:SYSTEM|PUBLIC)~i',$v)) $add('NX-230',$field);
                if (preg_match('/[\r\n]\s*(?:location|set-cookie|content-length|host)\s*:/i',$v)) $add('NX-240',$field);
            }
        };
        $walk($query,'query'); $walk($form,'post');
        return array_values($hits);
    }
}
