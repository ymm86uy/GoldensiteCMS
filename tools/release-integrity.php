<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';

use App\Core\{ProductEdition,ReleaseUpdate,SafeArchive};

$root=dirname(__DIR__);
$file=$argv[2]??'';
try {
    if (($argv[1]??'')==='manifest') {
        $zip=new ZipArchive();
        if (!is_file($file) || $zip->open($file)!==true || $zip->numFiles>10000) throw new RuntimeException('Instalador ZIP inválido.');
        $edition=trim((string)$zip->getFromName('release-edition.txt'));
        $version=trim((string)$zip->getFromName('release-version.txt'));
        if (!in_array($edition,['owner','commercial'],true) || !preg_match('/^\d+\.\d+\.\d+$/D',$version)) throw new RuntimeException('El ZIP debe ser un instalador versionado.');
        $files=[];
        for ($i=0;$i<$zip->numFiles;$i++) {
            $name=$zip->getNameIndex($i);
            if (str_ends_with($name,'/') || !SafeArchive::pathAllowed($name)) continue;
            $entry=$zip->statIndex($i);
            if (isset($files[$name]) || $entry['size']>10485760) throw new RuntimeException('Entrada de instalador inválida.');
            $contents=$zip->getFromIndex($i);
            if (!is_string($contents) || strlen($contents)!==$entry['size']) throw new RuntimeException('Entrada incompleta.');
            $files[$name]=hash('sha256',$contents);
        }
        $zip->close();
        ksort($files,SORT_STRING);
        echo json_encode(['edition'=>$edition,'version'=>$version,'files'=>$files],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if (($argv[1]??'')!=='verify' || !is_file($file) || filesize($file)>2097152) throw new RuntimeException('Uso: php tools/release-integrity.php manifest <instalador.zip> | verify <manifiesto.json>');
    $result=\App\Core\ReleaseIntegrity::compare($root,$file);
    ['checked'=>$checked,'missing'=>$missing,'changed'=>$changed,'expected'=>$expected]=$result;
    $extra=[];
    foreach (['app','database','modules','plugins','public','routes','tools','views'] as $directory) {
        if (!is_dir($root.'/'.$directory) || is_link($root.'/'.$directory)) continue;
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
        foreach ($files as $entry) {
            if (!$entry->isFile()) continue;
            $path=str_replace('\\','/',substr($entry->getPathname(),strlen($root)+1));
            if (SafeArchive::pathAllowed($path) && preg_match('/\.(?:php|sql|js|css)$/iD',$path) && !isset($expected[$path])) $extra[]=$path;
        }
    }
    sort($extra,SORT_STRING);
    echo json_encode(['checked'=>$checked,'missing'=>$missing,'changed'=>$changed,'extra_code'=>$extra],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit($missing || $changed || $extra?2:0);
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
}
