<?php
declare(strict_types=1);
// Dedicated, empty test database only; never uses the site's environment file.
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';
$name=getenv('GOLDENSITE_TEST_DB');
if (!is_string($name) || !preg_match('/^goldensite_test_[a-z0-9_]+$/D',$name)) throw new RuntimeException('Se necesita una base exclusiva de pruebas.');
putenv('DB_NAME='.$name);
putenv('GOLDENSITE_ENV_FILE='.__DIR__.'/no-site-environment');
$db=App\Core\Database::getInstance()->getConnection();
if ($db->query('SHOW TABLES')->fetchAll()) throw new RuntimeException('La base de prueba debe estar vacía.');
$checks=0;
$check=static function(bool $result,string $message) use (&$checks): void { $checks++; if (!$result) throw new RuntimeException($message); };
$db->exec("CREATE TABLE goldensite_chat_rooms (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(50) UNIQUE NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("INSERT INTO goldensite_chat_rooms (id,slug,name) VALUES (42,'existing','Sala conservada')");
App\Core\SchemaMigrator::migrate($db,null,['goldensite_chat_messages']);
$db->exec("INSERT INTO goldensite_chat_messages (sender_name,message,channel) VALUES ('existing-user','Mensaje conservado','existing')");
Plugins\GoldensiteChat\ChatEngine::initSchema();
$room=$db->query('SELECT * FROM goldensite_chat_rooms WHERE id=42')->fetch();
$check($room['slug']==='existing' && $room['name']==='Sala conservada' && $room['access_type']==='public','La migración perdió la sala existente o no añadió su control de acceso');
$check((int)$db->query("SELECT COUNT(*) FROM goldensite_chat_messages WHERE message='Mensaje conservado'")->fetchColumn()===1,'Se perdió un mensaje');
$check(count(Plugins\GoldensiteChat\ChatEngine::getRooms())===4,'No se crearon las salas base compatibles');
$db->exec("UPDATE goldensite_chat_rooms SET access_type='private',name='Nombre personalizado' WHERE slug='general'");
Plugins\GoldensiteChat\ChatEngine::initSchema();
$check($db->query("SELECT access_type FROM goldensite_chat_rooms WHERE slug='general'")->fetchColumn()==='private','Se sobrescribió la configuración de una sala');
$check(!in_array('users',$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),true),'La migración modular afectó al esquema de usuarios');
try { App\Core\SchemaMigrator::migrate($db,null,['not_a_cms_table']); $check(false,'Se admitió una tabla ajena al esquema'); }
catch (InvalidArgumentException) { $check(true,'Selección inválida rechazada'); }
$db->exec('RENAME TABLE goldensite_chat_rooms TO chat_test_unavailable');
try {
    try { Plugins\GoldensiteChat\ChatEngine::getRooms(); $check(false,'Un error de almacenamiento se presentó como lista vacía'); }
    catch (RuntimeException $error) { $check(str_contains($error->getMessage(),'Referencia ') && !str_contains($error->getMessage(),'SQLSTATE'),'El error no distingue indisponibilidad o expone datos internos'); }
} finally { $db->exec('RENAME TABLE chat_test_unavailable TO goldensite_chat_rooms'); }
echo "OK: $checks comprobaciones de migración de chat y conservación de datos.\n";
