<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';

$root=dirname(__DIR__);
$process=proc_open(['git','ls-files','-z'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null,['bypass_shell'=>true]);
if (!is_resource($process)) throw new RuntimeException('No se pudo leer el inventario de Git.');
fclose($pipes[0]);
$output=stream_get_contents($pipes[1]);
$errors=stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process)!==0 || $output==='') throw new RuntimeException('Se necesita una copia de código registrada en Git.');
$files=explode("\0",rtrim($output,"\0"));
$failures=[];
$phpFiles=0;
foreach ($files as $path) {
    if (preg_match('~^(?:vendor/|output/|uploads/|public/(?:uploads|cache|downloads|updates|assets/site-icons)/|storage/)(?!\\.htaccess$)~',$path)
        || preg_match('~(?:^|/)(?:\\.env(?:\\..*)?|license-private\\.pem|initial-access\\.json|recovery-credentials\\.json)$~',$path)
            && $path!=='.env.example') {
        $failures[]='Archivo privado o generado en Git — '.$path;
        continue;
    }
    $file=$root.'/'.$path;
    if (is_link($file) || !is_file($file)) { $failures[]='Archivo ausente o enlazado — '.$path; continue; }
    $contents=file_get_contents($file);
    if ($contents===false) { $failures[]='Archivo ilegible — '.$path; continue; }
    if (preg_match('/-----BEGIN (?:[A-Z0-9]+ )*PRIVATE KEY-----\\s+[A-Za-z0-9+\/=]{20,}/',$contents)
        || preg_match('/\\b(?:ghp_|github_pat_)[A-Za-z0-9_]{30,}/',$contents)) {
        $failures[]='Posible secreto en Git — '.$path;
    }
    if (preg_match('/\\.(?:php|phtml)$/iD',$path)) {
        $phpFiles++;
        try { token_get_all($contents,TOKEN_PARSE); }
        catch (ParseError) { $failures[]='Error de sintaxis PHP — '.$path; }
        if (str_starts_with($contents,"\xEF\xBB\xBF")) $failures[]='BOM antes del código PHP — '.$path;
    }
}
if ($failures) {
    fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);
    exit(1);
}
echo count($files).' archivos de Git revisados; '.$phpFiles.' archivos PHP válidos.'.PHP_EOL;
