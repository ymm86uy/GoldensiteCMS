<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(dirname(__DIR__).'/storage/module-worker.lock','c');
if (!$lock||!flock($lock,LOCK_EX|LOCK_NB)) exit("Ya existe un trabajador de módulos activo.\n");
try {
    $count=\App\Core\ModuleJobs::work();
    \App\Core\Settings::set('module_worker_seen',(string)time());
    echo "Tareas examinadas: $count\n";
} finally { flock($lock,LOCK_UN); fclose($lock); }
