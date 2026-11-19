<?php
declare(strict_types=1);
namespace App\Core;

use App\Security\{Clamd,UploadAntivirus,WafGateway};

final class HostingDiagnostics
{
    public static function report(string $root,array $server): array
    {
        $rows=[];
        $add=static function (string $id,string $name,string $state,string $detail,string $fix='') use (&$rows): void {
            $rows[]=compact('id','name','state','detail','fix')+['ok'=>$state==='checked'];
        };
        $public=realpath($root.'/public');
        $served=is_string($server['DOCUMENT_ROOT']??null) && $server['DOCUMENT_ROOT']!==''?realpath($server['DOCUMENT_ROOT']):false;
        $same=$public!==false && $served!==false && self::path($public)===self::path($served);
        $add('public-root','Raíz pública del alojamiento',$served===false?'unknown':($same?'checked':'warning'),
            $served===false?'El servidor no informó una raíz pública comprobable.':($same?'La raíz observada coincide con la carpeta pública del CMS.':'La raíz observada no coincide con la carpeta pública del CMS.'),
            'Configura la carpeta public como raíz del sitio. Si el proveedor exige otra disposición, revisa sus reglas de denegación para archivos privados.');
        $errors=!in_array(strtolower(trim((string)ini_get('display_errors'))),['','0','off','false','no','none'],true);
        $add('php-errors','Errores de PHP visibles',$errors?'warning':'checked',$errors?'PHP puede mostrar errores al visitante.':'PHP no muestra errores en esta ejecución.',
            'Desactiva display_errors en producción y conserva el registro privado de errores.');
        $https=!empty($server['HTTPS']) && strtolower((string)$server['HTTPS'])!=='off';
        $add('transport','HTTPS observado',$https?'checked':'unknown',$https?'Esta petición llegó a PHP mediante HTTPS.':'PHP no confirmó HTTPS. Un proxy puede terminar TLS antes de PHP.',
            'Comprueba el certificado y configura el proxy de confianza en el alojamiento. Las cabeceras enviadas por el visitante no se usan como prueba.');
        $mode=WafGateway::configuration()['mode'];
        $add('cms-waf','WAF de GoldenSiteCMS',$mode==='block'?'checked':'warning',
            $mode==='block'?'El motor está configurado para bloquear peticiones detectadas.':'El motor registra detecciones en modo de observación.',
            'Revisa las detecciones y aplica el modo de bloqueo desde el panel WAF.');
        $policy=UploadAntivirus::policy();
        $detail=match($policy) {
            'required'=>'Las cargas requieren análisis del motor local.',
            'disabled'=>'Las subidas están deshabilitadas por la política de esta instalación.',
            'provider'=>'La protección se delegó al proveedor y no fue comprobada por el CMS.',
            'invalid'=>'La política configurada no es válida.',
            default=>'Esta instalación no exige análisis antivirus automático.'
        };
        $add('upload-policy','Política global de cargas',in_array($policy,['required','disabled'],true)?'checked':($policy==='provider'?'unknown':'warning'),$detail,
            'Abre el panel WAF para revisar la política. En alojamiento restringido solicita al proveedor evidencia de su análisis; en un VPS puedes configurar ClamAV local.');
        $temporary=trim((string)ini_get('upload_tmp_dir'))?:sys_get_temp_dir();
        $resolved=realpath($temporary); $base=realpath($root);
        $outside=$resolved!==false && $base!==false && self::path($resolved)!==self::path($base)
            && !str_starts_with(self::path($resolved).'/',self::path($base).'/');
        $add('upload-temporary','Temporales de carga fuera del CMS',$resolved===false?'unknown':($outside && is_writable($resolved)?'checked':'warning'),
            $outside?'La carpeta temporal observada está fuera del proyecto.':'No se confirmó una carpeta temporal utilizable fuera del proyecto.',
            'Configura upload_tmp_dir fuera del CMS y de las carpetas públicas, con permisos limitados al servicio PHP. Esta comprobación solo valida su separación del proyecto actual.');
        $version=Clamd::endpoint()!==''?Clamd::version():null;
        $add('clamav-engine','Motor ClamAV',$version!==null?'checked':($policy==='required'?'warning':'unknown'),
            $version!==null?$version:'No se obtuvo una respuesta VERSION válida del motor local.',
            'Revisa el servicio y el socket local de ClamAV. Una respuesta de versión no sustituye una prueba de escaneo.');
        $signature=self::signatureAge($version,time());
        $add('clamav-signatures','Antigüedad declarada de firmas',$signature['state'],$signature['detail'],
            'Revisa FreshClam y sus registros. La fecha declarada no confirma que la base coincida con la última versión oficial.');
        $add('server-waf','ModSecurity y protección del proveedor','unknown',
            'El CMS no puede comprobar las reglas activas del servidor web ni la protección contra ataques de volumen.',
            'Solicita al administrador o proveedor evidencia de las reglas activas y sus registros. Un archivo de ejemplo o una cabecera HTTP no demuestra que estén aplicadas.');
        return ['checked_at'=>gmdate('c'),'checks'=>$rows];
    }

    public static function signatureAge(?string $version,int $now): array
    {
        if (!is_string($version) || !preg_match('~^ClamAV [^/]+/[0-9]+/(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun) (.+)$~D',$version,$match)) {
            return ['state'=>'unknown','detail'=>'La respuesta del motor no ofrece una fecha interpretable.'];
        }
        $date=\DateTimeImmutable::createFromFormat('!M j H:i:s Y',$match[1],new \DateTimeZone('UTC'));
        $errors=\DateTimeImmutable::getLastErrors();
        if (!$date || ($errors!==false && ($errors['warning_count'] || $errors['error_count'])) || $date->getTimestamp()>$now+86400) {
            return ['state'=>'unknown','detail'=>'La fecha declarada no es válida o está adelantada. Revisa los relojes del servidor.'];
        }
        $hours=max(0,intdiv($now-$date->getTimestamp(),3600));
        return ['state'=>$hours<=72?'checked':'warning','detail'=>'Fecha declarada por ClamAV: '.$date->format('Y-m-d H:i').' · antigüedad estimada '.$hours.' horas. Se interpreta como UTC.'];
    }

    private static function path(string $path): string
    {
        $path=rtrim(str_replace('\\','/',$path),'/');
        return PHP_OS_FAMILY==='Windows'?strtolower($path):$path;
    }
}
