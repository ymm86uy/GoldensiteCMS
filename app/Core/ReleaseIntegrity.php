<?php
declare(strict_types=1);
namespace App\Core;

final class ReleaseIntegrity
{
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
        $expected=$manifest['files']??null;
        if (!is_array($expected) || !$expected || count($expected)>10000 || ($manifest['edition']??null)!==ProductEdition::current()
            || ($manifest['version']??null)!==ReleaseUpdate::version()) throw new \RuntimeException('El manifiesto requiere la misma edición y versión que la instalación.');
        $missing=[]; $changed=[]; $skipped=0;
        $root=realpath($root);
        if ($root===false) throw new \RuntimeException('Raíz de instalación inválida.');
        foreach ($expected as $path=>$hash) {
            if (!is_string($path) || !SafeArchive::pathAllowed($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new \RuntimeException('Manifiesto de archivos inválido.');
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
