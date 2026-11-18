<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Database,SchemaMigrator,SecretStore,Settings,WebhookEngine};
$db=Database::getInstance()->getConnection(); SchemaMigrator::migrate($db);
if (Settings::get('hooks_url')==='') {
    $rows=$db->query('SELECT setting_key,setting_value FROM goldensite_webhook_settings')->fetchAll(PDO::FETCH_KEY_PAIR); $url=$rows['discord_webhook_url']??'';
    if ($url!=='') { try { WebhookEngine::url($url,'discord'); Settings::writeMany(['hooks_provider'=>'discord','hooks_url'=>SecretStore::seal($url),'hooks_enabled'=>($rows['webhook_enabled']??'0')==='1'?'1':'0','hooks_events'=>json_encode(['post_published'],JSON_THROW_ON_ERROR)]); } catch (InvalidArgumentException $e) { echo "El endpoint anterior necesita revisión desde la consola de webhooks.\n"; } }
}
if (($ftp=Settings::get('ftp_pass'))!==''&&!str_starts_with($ftp,'enc:v1:')) Settings::set('ftp_pass',SecretStore::seal($ftp));
echo "Módulos migrados. Datos y credenciales preservados; secretos locales cifrados.\n";
