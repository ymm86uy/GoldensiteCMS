<?php
namespace App\Core;

use AsyncAws\S3\S3Client;
use Symfony\Component\HttpClient\HttpClient;

final class S3Replica
{
    private const MAX_BYTES = 20971520;
    private S3Client $client;
    private string $bucket;
    private string $region;

    public static function configured(): bool
    {
        $bucket = Environment::get('GOLDENSITE_S3_BUCKET');
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D',$bucket)
            && preg_match('/^[a-z]{2}(?:-[a-z0-9]+)+-[0-9]+$/D',Environment::get('GOLDENSITE_S3_REGION'))
            && Environment::get('GOLDENSITE_S3_ACCESS_KEY_ID')!=='' && Environment::get('GOLDENSITE_S3_SECRET_ACCESS_KEY')!=='';
    }

    public function __construct(?S3Client $client = null)
    {
        if ($client!==null && PHP_SAPI!=='cli') throw new \LogicException('Cliente de prueba reservado a consola.');
        if (!self::configured()) throw new \InvalidArgumentException('Configura el bucket privado, la región y las credenciales de S3 en el servidor.');
        $this->bucket = Environment::get('GOLDENSITE_S3_BUCKET');
        $this->region = Environment::get('GOLDENSITE_S3_REGION');
        $credentials = [
            'region'=>$this->region,
            'accessKeyId'=>Environment::get('GOLDENSITE_S3_ACCESS_KEY_ID'),
            'accessKeySecret'=>Environment::get('GOLDENSITE_S3_SECRET_ACCESS_KEY'),
        ];
        $session = Environment::get('GOLDENSITE_S3_SESSION_TOKEN');
        if ($session!=='') $credentials['sessionToken'] = $session;
        $this->client = $client ?? new S3Client($credentials,null,HttpClient::create(['timeout'=>20,'max_duration'=>45,'max_redirects'=>0]));
    }

    public function copy(string $library,string $reference): void
    {
        $path = $this->source($library,$reference);
        $body = file_get_contents($path,false,null,0,self::MAX_BYTES+1);
        if ($body===false || $body==='' || strlen($body)>self::MAX_BYTES) throw new \InvalidArgumentException('Solo se admiten archivos de 1 byte a 20 MB.');
        $hash = hash('sha256',$body);
        $prefix = substr(hash_hmac('sha256','s3-replica',KeyStore::vault()),0,24);
        $object = 'goldensite/'.$prefix.'/'.$library.'/'.hash('sha256',$reference."\0".$hash);
        $length = strlen($body);
        $nonce = random_bytes(12);
        $encrypted = openssl_encrypt($body,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$nonce,$tag,$object,16);
        if ($encrypted===false) throw new \RuntimeException('No se pudo cifrar la copia remota.');
        $body = $nonce.$tag.$encrypted;
        $result = $this->client->putObject([
            'Bucket'=>$this->bucket,'Key'=>$object,'Body'=>$body,'ContentType'=>'application/octet-stream',
            'ChecksumSHA256'=>base64_encode(hash('sha256',$body,true)),'ServerSideEncryption'=>'AES256',
        ]);
        if (!$result->resolve(45)) throw new \RuntimeException('S3 no confirmó el archivo.');
        Database::getInstance()->execute('INSERT INTO goldensite_s3_replicas (library,resource_ref,bucket,region,object_key,sha256,bytes)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE created_at=NOW(),bucket=VALUES(bucket),region=VALUES(region)',
            [$library,$reference,$this->bucket,$this->region,$object,$hash,$length]);
    }

    public function restore(int $id): void
    {
        $row = Database::getInstance()->fetch('SELECT * FROM goldensite_s3_replicas WHERE id=?',[$id]);
        if (!$row || $row['bucket']!==$this->bucket || $row['region']!==$this->region) {
            throw new \InvalidArgumentException('Selecciona una copia del bucket y región configurados.');
        }
        $target = $this->target($row);
        if (file_exists($target) || is_link($target)) throw new \InvalidArgumentException('Ya existe un archivo local en ese destino.');
        $temporary = dirname($target).'/.s3-'.bin2hex(random_bytes(12));
        $output = fopen($temporary,'xb');
        if (!$output) throw new \RuntimeException('No se pudo preparar la restauración.');
        try {
            $result = $this->client->getObject(['Bucket'=>$this->bucket,'Key'=>$row['object_key']]);
            $sealed = '';
            foreach ($result->getBody()->getChunks() as $chunk) {
                if (strlen($sealed)+strlen($chunk)>(int)$row['bytes']+28 || strlen($sealed)+strlen($chunk)>self::MAX_BYTES+28) {
                    throw new \RuntimeException('La copia remota no coincide con el tamaño esperado.');
                }
                $sealed .= $chunk;
            }
            if (strlen($sealed)!==(int)$row['bytes']+28) throw new \RuntimeException('La copia remota está incompleta.');
            $plain = openssl_decrypt(substr($sealed,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($sealed,0,12),substr($sealed,12,16),$row['object_key']);
            if ($plain===false || !hash_equals($row['sha256'],hash('sha256',$plain))) {
                throw new \RuntimeException('La copia remota no supera la verificación de integridad.');
            }
            if (fwrite($output,$plain)!==strlen($plain)) throw new \RuntimeException('La escritura local quedó incompleta.');
            fclose($output);
            $output = null;
            chmod($temporary,$row['library']==='downloads'?0600:0644);
            if (!@link($temporary,$target)) throw new \RuntimeException('No se pudo restaurar sin reemplazar archivos.');
        } finally {
            if (is_resource($output)) fclose($output);
            @unlink($temporary);
        }
    }

    private function source(string $library,string $reference): string
    {
        if ($library==='dam') {
            self::mediaReference($reference);
            return AssetLibrary::path($reference);
        }
        if ($library==='downloads') {
            $file = $this->download($reference);
            $path = DownloadLibrary::path($file);
            if ($path) return $path;
        }
        throw new \InvalidArgumentException('Selecciona un recurso local disponible.');
    }

    private function download(string $reference): array
    {
        $row = ctype_digit($reference)?Database::getInstance()->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status<>'deleted'",[$reference]):null;
        if (!$row) throw new \InvalidArgumentException('Descarga no disponible.');
        return $row;
    }

    private function target(array $row): string
    {
        $root = dirname(__DIR__,2);
        if ($row['library']==='downloads') {
            $file = $this->download($row['resource_ref']);
            if (!preg_match('/^vault_[a-f0-9]{32}\.bin$/D',$file['file_path'])
                || ($file['sha256']!==null && !hash_equals($file['sha256'],$row['sha256']))) {
                throw new \InvalidArgumentException('La copia no corresponde al archivo actual de la biblioteca.');
            }
            $base = $root.'/storage/downloads';
            $relative = $file['file_path'];
        } elseif ($row['library']==='dam') {
            $base = $root.'/public/uploads';
            $relative = $row['resource_ref'];
            self::mediaReference($relative);
        } else {
            throw new \InvalidArgumentException('Biblioteca inválida.');
        }
        $parent = realpath(dirname($base.'/'.$relative));
        $anchor = realpath($base);
        if (!$parent || !$anchor || is_link($base) || !str_starts_with(str_replace('\\','/',$parent).'/',str_replace('\\','/',$anchor).'/')) {
            throw new \InvalidArgumentException('La carpeta local de destino no está disponible.');
        }
        return $parent.'/'.basename($relative);
    }

    private static function mediaReference(string $reference): void
    {
        if (!preg_match('~^(?:[a-zA-Z0-9_ -][a-zA-Z0-9_. -]*/)*[a-zA-Z0-9_ -][a-zA-Z0-9_. -]*\.(?:jpg|jpeg|png|gif|webp|pdf|txt|csv|zip)$~iD',$reference)
            || str_contains($reference,'..')) throw new \InvalidArgumentException('Selecciona una ruta de archivo admitida por DAM.');
    }

    private static function key(): string
    {
        return hash_hmac('sha256',LegacyFormat::PREFIX.':s3-replica:v1',KeyStore::vault(),true);
    }
}
