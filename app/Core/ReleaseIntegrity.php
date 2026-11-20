<?php
declare(strict_types=1);
namespace App\Core;

final class ReleaseIntegrity
{
    public const REFERENCE = 'app/release-integrity.nfx';

    /** One canonical archive reader for release tooling and signed references. */
    public static function archiveManifest(string $archive): array
    {
        $zip=new \ZipArchive();
        if (!is_file($archive) || $zip->open($archive)!==true) throw new \RuntimeException('Instalador ZIP inválido.');
        try {
            $edition=trim((string)$zip->getFromName('release-edition.txt'));
            $version=trim((string)$zip->getFromName('release-version.txt'));
            if ($zip->numFiles>10000 || !in_array($edition,['owner','commercial'],true) || !preg_match('/^\d+\.\d+\.\d+$/D',$version)) throw new \RuntimeException('Instalador versionado inválido.');
            $files=[]; $bytes=0;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $entry=$zip->statIndex($i); $name=$entry['name'];
                if ($name===self::REFERENCE || str_ends_with($name,'/') || !SafeArchive::pathAllowed($name)) continue;
                $bytes+=$entry['size'];
                if (isset($files[$name]) || $entry['size']>10485760 || $bytes>104857600) throw new \RuntimeException('Entrada de instalador inválida.');
                $contents=$zip->getFromIndex($i);
                if (!is_string($contents) || strlen($contents)!==$entry['size']) throw new \RuntimeException('Entrada incompleta.');
                $files[$name]=hash('sha256',$contents);
            }
            ksort($files,SORT_STRING);
            return ['edition'=>$edition,'version'=>$version,'files'=>$files];
        } finally { $zip->close(); }
    }

    public static function reference(string $root): array
    {
        $path=$root.'/'.self::REFERENCE;
        if (is_link($path) || !is_file($path) || filesize($path)>2097152) throw new \RuntimeException('Referencia oficial no disponible.');
        $manifest=LicenseCode::open(trim((string)file_get_contents($path)),'NFXRI1',2097152);
        self::validate($manifest);
        return $manifest;
    }

    private static function validate(array $manifest): void
    {
        $expected=$manifest['files']??null;
        if (!is_array($expected) || !$expected || count($expected)>10000 || ($manifest['edition']??null)!==ProductEdition::current()
            || ($manifest['version']??null)!==ReleaseUpdate::version()) throw new \RuntimeException('La referencia requiere la misma edición y versión que la instalación.');
        foreach ($expected as $path=>$hash) {
            if (!is_string($path) || $path===self::REFERENCE || !SafeArchive::pathAllowed($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new \RuntimeException('Manifiesto de archivos inválido.');
        }
    }

    private static function fingerprint(array $manifest): string
    {
        return hash('sha256',json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    /** Returns historical evidence, never a claim that the live site is free of vulnerabilities. */
    public static function status(): array
    {
        $root=dirname(__DIR__,2);
        try { $reference=self::reference($root); }
        catch (\Throwable) { return ['state'=>'unavailable']; }
        $raw=Settings::get('release_integrity_report','');
        if (strlen($raw)>65536) return ['state'=>'unchecked'];
        try {
            $record=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
            $payload=$record['payload']??''; $mac=$record['mac']??'';
            if (!is_string($payload) || !is_string($mac) || !hash_equals(hash_hmac('sha256',"release-integrity-report\0".$payload,KeyStore::vault()),$mac)) return ['state'=>'unchecked'];
            $result=json_decode($payload,true,10,JSON_THROW_ON_ERROR);
            if (($result['reference']??null)!==self::fingerprint($reference) || !is_int($result['time']??null) || $result['time']>time() || $result['time']<1) return ['state'=>'unchecked'];
            if (time()-$result['time']>86400) $result['state']='stale';
            return $result;
        } catch (\Throwable) { return ['state'=>'unchecked']; }
    }

    public static function run(): array
    {
        $root=dirname(__DIR__,2); $reference=self::reference($root);
        $result=self::compareManifest($root,$reference,134217728,8.0);
        $report=['state'=>($result['missing'] || $result['changed'])?'differences':($result['skipped']?'partial':'matching'),
            'time'=>time(),'reference'=>self::fingerprint($reference),'checked'=>$result['checked'],'total'=>count($result['expected']),
            'missing_count'=>count($result['missing']),'changed_count'=>count($result['changed']),'skipped'=>$result['skipped'],
            'missing'=>array_slice($result['missing'],0,30),'changed'=>array_slice($result['changed'],0,30)];
        $payload=json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        Settings::set('release_integrity_report',json_encode(['payload'=>$payload,'mac'=>hash_hmac('sha256',"release-integrity-report\0".$payload,KeyStore::vault())],JSON_THROW_ON_ERROR));
        return $report;
    }

    /** Shared guard used by both existing administration screens. */
    public static function checkRequest(): void
    {
        RequestGuard::post();
        if (!IdentityAccess::superuser()) throw new \InvalidArgumentException('Solo un superadministrador puede comprobar los archivos.');
        if (!RateLimiter::consume('release-integrity:'.Auth::id(),3,900) || !RateLimiter::consume('release-integrity:installation',1,60)) throw new \InvalidArgumentException('Espera antes de iniciar otra comprobación.');
        ConsoleView::password();
        self::run();
        ConsoleView::audit('release_integrity_check','Comprobación de archivos oficiales finalizada.');
    }

    /** Inspect declarations without executing application code or loading its configuration. */
    public static function duplicateClasses(string $root): array
    {
        $classes=[];
        foreach (['app','config','modules','plugins','public','routes','tools','views'] as $scope) {
            $directory=$root.'/'.$scope;
            if (!is_dir($directory) || is_link($directory)) continue;
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension())!=='php') continue;
                $path=str_replace('\\','/',substr($file->getPathname(),strlen(rtrim($root,'/\\'))+1));
                if (preg_match('~^public/(?:uploads|cache|downloads|updates|assets/site-icons)/|\.(?:bak|bak\.php)$| - copia\.php$~i',$path)) continue;
                $tokens=token_get_all((string)file_get_contents($file->getPathname()),TOKEN_PARSE);
                $namespace='';
                foreach ($tokens as $index=>$token) {
                    if (!is_array($token)) continue;
                    if ($token[0]===T_NAMESPACE) {
                        $namespace='';
                        for ($next=$index+1;isset($tokens[$next]);$next++) {
                            $part=$tokens[$next];
                            if ($part===';' || $part==='{') break;
                            if (is_array($part) && in_array($part[0],[T_STRING,T_NAME_QUALIFIED,T_NS_SEPARATOR],true)) $namespace.=$part[1];
                        }
                    }
                    if (!in_array($token[0],[T_CLASS,T_INTERFACE,T_TRAIT,T_ENUM],true)) continue;
                    for ($next=$index+1;isset($tokens[$next]);$next++) {
                        $name=$tokens[$next];
                        if (is_array($name) && in_array($name[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) continue;
                        if (is_array($name) && $name[0]===T_STRING) {
                            $key=strtolower(ltrim($namespace.'\\'.$name[1],'\\'));
                            $classes[$key][]=['path'=>$path,'line'=>$token[2]];
                        }
                        break;
                    }
                }
            }
        }
        return array_filter($classes,static fn(array $declarations): bool=>count($declarations)>1);
    }

    public static function compare(string $root,string $file,?int $budget=null): array
    {
        if (is_link($file) || !is_file($file) || filesize($file)>2097152) throw new \RuntimeException('Manifiesto inválido.');
        $manifest=json_decode((string)file_get_contents($file,false,null,0,2097153),true,8,JSON_THROW_ON_ERROR);
        return self::compareManifest($root,$manifest,$budget);
    }

    public static function compareOfficial(string $root,?int $budget=null): array
    {
        return self::compareManifest($root,self::reference($root),$budget,8.0);
    }

    private static function compareManifest(string $root,array $manifest,?int $budget=null,?float $seconds=null): array
    {
        self::validate($manifest);
        $expected=$manifest['files']; $deadline=$seconds===null?INF:microtime(true)+$seconds;
        $missing=[]; $changed=[]; $skipped=0;
        $root=realpath($root);
        if ($root===false) throw new \RuntimeException('Raíz de instalación inválida.');
        foreach ($expected as $path=>$hash) {
            if (microtime(true)>$deadline) { $skipped++; continue; }
            $candidate=$root.'/'.$path;
            $resolved=realpath($candidate);
            if ($resolved===false || !is_file($candidate)) { $missing[]=$path; continue; }
            if (is_link($candidate) || !str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)) { $changed[]=$path; continue; }
            if ($budget!==null) {
                $size=@filesize($candidate);
                if ($size===false || $size>$budget || $size>10485760) { $skipped++; continue; }
                $budget-=$size;
            }
            $actual=@hash_file('sha256',$candidate);
            if (!is_string($actual) || !hash_equals($hash,$actual)) $changed[]=$path;
        }
        return ['checked'=>count($expected)-$skipped,'missing'=>$missing,'changed'=>$changed,'skipped'=>$skipped,'expected'=>$expected];
    }
}
