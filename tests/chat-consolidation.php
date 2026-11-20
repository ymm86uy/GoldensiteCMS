<?php
// Installed fixture only. Fault injection is isolated from general backup/import tests.
checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'owner@example.test','password'=>$password])['status']===302,'No se pudo acceder a la prueba del chat.');
$csrf=installCsrf(installRequest('/admin/plugins'));
$db->exec("INSERT INTO goldensite_module_offers (module_id,tier,amount_cents,currency,period) VALUES ('live-chat','free',0,'USD','once') ON DUPLICATE KEY UPDATE tier='free'");
installRequest('/admin/plugins',['csrf_token'=>$csrf,'action_toggle_module'=>1,'module_id'=>'live-chat','toggle_state'=>'1']);
checkInstall(installRequest('/chat')['status']===200,'El chat no abre en la instalación nueva.');
$good=installRequest('/api/chat/send',['csrf_token'=>$csrf,'message'=>'Mensaje conservado','channel'=>'general']);
checkInstall($good['status']===200 && (json_decode($good['body'],true)['success']??false),'El chat no publica normalmente.');
$beforeFailure=(int)$db->query('SELECT COUNT(*) FROM goldensite_chat_messages')->fetchColumn();
$db->exec('RENAME TABLE goldensite_chat_sanctions TO goldensite_chat_sanctions_fixture');
try {
    $failedChat=installRequest('/chat',['csrf_token'=>$csrf,'message'=>'must-not-publish']);
    checkInstall($failedChat['status']===503 && !str_contains($failedChat['body'],'SQLSTATE'),'Una sanción no comprobable no detiene el chat de forma segura');
    $failedApi=installRequest('/api/chat/send',['csrf_token'=>$csrf,'message'=>'must-not-publish','channel'=>'general']);
    $failedPayload=json_decode($failedApi['body'],true);
    checkInstall($failedApi['status']===503 && ($failedPayload['success']??true)===false,'La API anuncia éxito cuando no puede comprobar sanciones');
    checkInstall((int)$db->query('SELECT COUNT(*) FROM goldensite_chat_messages')->fetchColumn()===$beforeFailure,'Se publicó omitiendo la comprobación de sanciones');
    checkInstall(!$db->query("SHOW TABLES LIKE 'goldensite_chat_sanctions'")->fetchColumn(),'Una petición recreó la tabla de sanciones sin sus datos');
} finally { $db->exec('RENAME TABLE goldensite_chat_sanctions_fixture TO goldensite_chat_sanctions'); }
checkInstall(installRequest('/api/chat/messages?channel=general')['status']===200,'El chat no se recupera al restablecer el almacenamiento.');
checkInstall(str_contains(file_get_contents($log),'Goldensite chat '),'No se registró el fallo simulado.');
echo "OK: $checks comprobaciones de instalación y chat ante fallo de almacenamiento.\n";
