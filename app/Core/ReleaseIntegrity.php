<?php
declare(strict_types=1);
namespace App\Core;

final class ReleaseIntegrity
{
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
