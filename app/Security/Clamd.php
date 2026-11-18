<?php
declare(strict_types=1);
namespace App\Security;

use App\Core\Environment;

final class Clamd
{
    public const LIMIT=20971520;

    public static function endpoint(): string
    {
        return trim(Environment::get('GOLDENSITE_CLAMD_ENDPOINT'));
    }

    public static function version(): ?string
    {
        if (self::endpoint()==='') return null;
        try {
            $socket=self::connect();
            try {
                $deadline=hrtime(true)+2_000_000_000;
                self::write($socket,"zVERSION\0",$deadline);
                $version=self::reply($socket,$deadline);
                return preg_match('~^ClamAV [^\x00-\x1f/]+/[0-9]+/[^\x00-\x1f]+$~D',$version)?$version:null;
            } finally { fclose($socket); }
        } catch (\RuntimeException) { return null; }
    }

    public static function clean(string $path,int $size,int $deadline): bool
    {
        if ($size<1 || $size>self::LIMIT) throw new \RuntimeException('Tamaño de análisis inválido.');
        $input=@fopen($path,'rb');
        if (!$input) throw new \RuntimeException('No se pudo leer la carga.');
        try {
            $socket=self::connect();
            try {
                self::write($socket,"zINSTREAM\0",$deadline);
                for ($remaining=$size;$remaining>0;$remaining-=strlen($chunk)) {
                    $chunk=fread($input,min(65536,$remaining));
                    if ($chunk===false || $chunk==='') throw new \RuntimeException('Carga incompleta.');
                    self::write($socket,pack('N',strlen($chunk)).$chunk,$deadline);
                }
                if (fread($input,1)!=='') throw new \RuntimeException('La carga cambió durante el análisis.');
                self::write($socket,pack('N',0),$deadline);
                $reply=self::reply($socket,$deadline);
                if ($reply==='stream: OK') return true;
                if (preg_match('/^stream: [^\x00-\x1f]+ FOUND$/D',$reply)) return false;
                throw new \RuntimeException('El motor no completó el análisis.');
            } finally { fclose($socket); }
        } finally { fclose($input); }
    }

    private static function connect()
    {
        $endpoint=self::endpoint();
        if (!preg_match('~^(?:unix:///[^\x00-\x20]+|tcp://(?:127\.0\.0\.1|\[::1\]):[0-9]{1,5})$~D',$endpoint)) {
            throw new \RuntimeException('Configura un socket local de ClamAV.');
        }
        $socket=@stream_socket_client($endpoint,$code,$error,1.0);
        if (!$socket) throw new \RuntimeException('ClamAV no está disponible.');
        return $socket;
    }

    private static function timeout($socket,int $deadline): void
    {
        $microseconds=intdiv($deadline-hrtime(true),1000);
        if ($microseconds<1) throw new \RuntimeException('El análisis superó su tiempo límite.');
        stream_set_timeout($socket,intdiv($microseconds,1000000),$microseconds%1000000);
    }

    private static function write($socket,string $bytes,int $deadline): void
    {
        $length=strlen($bytes);
        for ($offset=0;$offset<$length;$offset+=$count) {
            self::timeout($socket,$deadline);
            $count=@fwrite($socket,substr($bytes,$offset));
            if (!$count) throw new \RuntimeException('Se interrumpió el envío a ClamAV.');
        }
    }

    private static function reply($socket,int $deadline): string
    {
        $reply='';
        while (strlen($reply)<4096) {
            self::timeout($socket,$deadline);
            $chunk=@fread($socket,1024);
            if ($chunk===false || $chunk==='') break;
            $reply.=$chunk;
            $end=strpos($reply,"\0");
            if ($end!==false && $end<4096) return substr($reply,0,$end);
        }
        throw new \RuntimeException('Respuesta de ClamAV incompleta.');
    }
}
