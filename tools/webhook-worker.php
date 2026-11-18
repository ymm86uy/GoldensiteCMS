<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(dirname(__DIR__).'/storage/webhook-worker.lock','c'); if (!$lock||!flock($lock,LOCK_EX|LOCK_NB)) exit("Hay otro trabajador de webhooks activo.\n");
try { $count=\App\Core\WebhookEngine::work(); \App\Core\Settings::set('webhook_worker_seen',(string)time()); echo "Webhooks procesados: $count\n"; } finally { flock($lock,LOCK_UN); fclose($lock); }
