<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';

use App\Core\{ProductEdition,ReleaseUpdate,SafeArchive};

$root=dirname(__DIR__);
$file=$argv[2]??'';
try {
    if (($argv[1]??'')==='manifest') {
        echo json_encode(\App\Core\ReleaseIntegrity::archiveManifest($file),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
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
            if (SafeArchive::pathAllowed($path) && preg_match('/\.(?:php|sql|js|css)$/iD',$path) && $path!==\App\Core\ReleaseIntegrity::REFERENCE && !isset($expected[$path])) $extra[]=$path;
        }
    }
    sort($extra,SORT_STRING);
    echo json_encode(['checked'=>$checked,'missing'=>$missing,'changed'=>$changed,'extra_code'=>$extra],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit($missing || $changed || $extra?2:0);
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
}
