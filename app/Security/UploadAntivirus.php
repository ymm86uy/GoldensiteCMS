<?php
declare(strict_types=1);
namespace App\Security;

use App\Core\{Auth,Environment,IdentityAccess,RateLimiter};

final class UploadAntivirus
{
    public static function policy(): string
    {
        $policy=strtolower(trim(Environment::get('GOLDENSITE_UPLOAD_POLICY','auto')));
        if ($policy==='' || $policy==='auto') return Clamd::endpoint()===''?'unverified':'required';
        return in_array($policy,['required','provider','disabled'],true)?$policy:'invalid';
    }

    public static function inspect(array $files): void
    {
        if (!$files) return;
        $policy=self::policy();
        if ($policy==='unverified' || $policy==='provider') return;
        $id=bin2hex(random_bytes(16));
        $ip=WafAccess::address($_SERVER['REMOTE_ADDR']??'');
        $queue=[];
        $total=0;
        foreach ($files as $file) {
            foreach (self::temporaryFiles($file['error'],$file['tmp_name']) as $path) {
                if (!is_uploaded_file($path)) self::reject(400,'La carga recibida no es válida.',$id);
                if ($policy==='disabled') self::reject(403,'Esta instalación tiene deshabilitadas las subidas de archivos.',$id);
                if ($policy==='invalid' || Clamd::endpoint()==='') self::reject(503,'Falta configurar el análisis de archivos de esta instalación.',$id);
                if (!self::outsideProject($path)) {
                    error_log('Goldensite antivirus '.$id.': configura upload_tmp_dir fuera del directorio del CMS.');
                    self::reject(503,'La ubicación temporal de las cargas no es segura.',$id);
                }
                $size=filesize($path);
                if ($size===false) self::reject(503,'El análisis de la carga no está disponible.',$id);
                // Empty, optional multipart parts cannot carry executable content.
                if ($size===0) continue;
                $total+=$size;
                if ($total>Clamd::LIMIT || count($queue)>=10) self::reject(413,'La carga admite hasta 10 archivos y 20 MB en total.',$id);
                $queue[]=[$path,$size];
            }
        }
        if (!$queue) return;
        $staff=Auth::check() && IdentityAccess::canEnter();
        if (!RateLimiter::consume('clamav:'.$ip,$staff?30:8,60)) {
            header('Retry-After: 60');
            self::reject(429,'Espera un minuto antes de volver a subir archivos.',$id);
        }
        $deadline=hrtime(true)+12_000_000_000;
        try {
            foreach ($queue as [$path,$size]) {
                if (!Clamd::clean($path,$size,$deadline)) {
                    $hit=['id'=>'NX-500','field'=>'upload']+WafPolicy::rules()['NX-500'];
                    WafGateway::autoban($id,['NX-500'],$ip,$staff,true);
                    WafGateway::record($id,[$hit],'BLOCK');
                    self::reject(403,'El antivirus rechazó la carga.',$id);
                }
            }
        } catch (\RuntimeException $error) {
            error_log('Goldensite antivirus '.$id.': '.$error->getMessage());
            header('Retry-After: 30');
            self::reject(503,'El antivirus no pudo verificar la carga. Intenta más tarde.',$id);
        }
    }

    private static function outsideProject(string $file): bool
    {
        $root=realpath(dirname(__DIR__,2));
        $path=realpath($file);
        if ($root===false || $path===false) return false;
        $root=str_replace('\\','/',$root).'/';
        $path=str_replace('\\','/',$path);
        return !(PHP_OS_FAMILY==='Windows'?str_starts_with(strtolower($path),strtolower($root)):str_starts_with($path,$root));
    }

    private static function temporaryFiles(int|array $errors,string|array $paths): iterable
    {
        if (is_array($errors) && is_array($paths)) {
            foreach ($errors as $key=>$error) yield from self::temporaryFiles($error,$paths[$key]);
        } elseif ($errors===UPLOAD_ERR_OK && is_string($paths)) {
            yield $paths;
        }
    }

    private static function reject(int $status,string $message,string $id): never
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('X-Goldensite-Request-ID: '.$id);
        header('Content-Type: text/plain; charset=utf-8');
        exit($message."\nReferencia ".$id);
    }
}
