<?php
// Runs only against an isolated installer fixture; never the developer's own site.
function fixtureIntegrity(string $code): array {
    global $fixture,$environment;
    $script=$fixture.'/storage/test-integrity.php';
    file_put_contents($script,"<?php\nrequire dirname(__DIR__).'/vendor/autoload.php';\nApp\\Core\\Environment::load(dirname(__DIR__).'/.env');\n".$code);
    $process=proc_open([PHP_BINARY,$script],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$fixture,$environment,['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('No se pudo ejecutar la prueba aislada.');
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process)!==0) throw new RuntimeException('Prueba de integridad: '.$err);
    return json_decode($out,true,12,JSON_THROW_ON_ERROR);
}
checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'owner@example.test','password'=>$password])['status']===302,'No se pudo acceder a la prueba de integridad.');
$dashboard=installRequest('/admin/dashboard');
checkInstall($dashboard['status']===200 && str_contains(html_entity_decode($dashboard['body'],ENT_QUOTES|ENT_HTML5,'UTF-8'),'Integridad de la instalación'),'Falta la vista de integridad.');
$csrf=installCsrf($dashboard);
checkInstall(installRequest('/admin/dashboard',['action'=>'check_integrity','current_password'=>$password])['status']===403,'La comprobación permite omitir CSRF.');
checkInstall(installRequest('/admin/dashboard',['csrf_token'=>$csrf,'action'=>'check_integrity','current_password'=>'wrong'])['status']===422,'La comprobación permite una contraseña incorrecta.');
// Clear only this fixture's limits after the deliberate authentication failure.
fixtureIntegrity('App\Core\RateLimiter::clear("release-integrity:1"); App\Core\RateLimiter::clear("release-integrity:installation"); echo "[]";');
$post=installRequest('/admin/dashboard',['csrf_token'=>$csrf,'action'=>'check_integrity','current_password'=>$password]);
checkInstall($post['status']===302,'No se ejecutó la comprobación autenticada.');
$result=fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::status());');
$repacked=getenv('GOLDENSITE_TEST_INTEGRITY_REPACKED')==='1';
checkInstall(($result['state']==='matching' || ($repacked && $result['state']==='differences')) && $result['checked']>500 && $result['skipped']===0,'La instalación oficial no coincide con su referencia firmada.');
checkInstall(installRequest('/admin/dashboard',['csrf_token'=>$csrf,'action'=>'check_integrity','current_password'=>$password])['status']===422,'No se aplica el límite de frecuencia.');
$path=$fixture.'/views/cookie-policy.php'; $original=file_get_contents($path);
try {
    file_put_contents($path,$original."\n<!-- controlled integrity test -->");
    $changed=fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::run());');
    checkInstall($changed['state']==='differences' && in_array('views/cookie-policy.php',$changed['changed'],true),'No detecta un archivo modificado.');
    unlink($path);
    $missing=fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::run());');
    checkInstall($missing['state']==='differences' && in_array('views/cookie-policy.php',$missing['missing'],true),'No detecta un archivo faltante.');
} finally { file_put_contents($path,$original); }
$reference=$fixture.'/app/release-integrity.nfx'; $signed=file_get_contents($reference);
try {
    file_put_contents($reference,substr($signed,0,25).'tampered'.substr($signed,33));
    checkInstall(fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::status());')['state']==='unavailable','Acepta una referencia con firma alterada.');
} finally { file_put_contents($reference,$signed); }
$record=$db->query("SELECT setting_value FROM settings WHERE setting_key='release_integrity_report'")->fetchColumn();
$forged=json_decode($record,true); $payload=json_decode($forged['payload'],true);$payload['state']='matching';$forged['payload']=json_encode($payload);
$db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='release_integrity_report'")->execute([json_encode($forged)]);
checkInstall(fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::status());')['state']==='unchecked','Acepta un informe manipulado.');
$final=fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::run());');
checkInstall($final['state']===$result['state'] && $final['missing']===$result['missing'] && $final['changed']===$result['changed'],'La instalación restaurada no vuelve al resultado inicial.');
$limited=fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::compareOfficial(dirname(__DIR__),1));');
checkInstall($limited['skipped']>0,'Un presupuesto insuficiente no se declara como parcial.');
$stale=fixtureIntegrity('$raw=json_decode(App\Core\Settings::get("release_integrity_report"),true); $data=json_decode($raw["payload"],true); $data["time"]=time()-86401; $payload=json_encode($data); App\Core\Settings::set("release_integrity_report",json_encode(["payload"=>$payload,"mac"=>hash_hmac("sha256","release-integrity-report\0".$payload,App\Core\KeyStore::vault())])); echo json_encode(App\Core\ReleaseIntegrity::status());');
checkInstall($stale['state']==='stale','Un informe antiguo sigue apareciendo vigente.');
fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::run());');
// Invalidate a correctly authenticated old result after changing the installed version marker.
$version=file_get_contents($fixture.'/release-version.txt');
try {
    file_put_contents($fixture.'/release-version.txt',"99.0.0\n");
    checkInstall(fixtureIntegrity('echo json_encode(App\Core\ReleaseIntegrity::status());')['state']==='unavailable','Una versión distinta reutiliza una referencia anterior.');
} finally { file_put_contents($fixture.'/release-version.txt',$version); }
checkInstall(installRequest('/admin/dashboard')['status']===200,'El Dashboard dejó de abrir tras las comprobaciones.');
$previousTier=$db->query("SELECT tier FROM goldensite_module_offers WHERE module_id='release-backup'")->fetchColumn();
try {
    $db->exec("INSERT INTO goldensite_module_offers (module_id,tier,amount_cents,currency,period) VALUES ('release-backup','free',0,'USD','once') ON DUPLICATE KEY UPDATE tier='free'");
    $backup=installRequest('/admin/backups');
    checkInstall($backup['status']===200 && str_contains($backup['body'],'release-integrity-heading'),'Release Manager no reutiliza la vista de integridad.');
} finally {
    if ($previousTier===false) $db->exec("DELETE FROM goldensite_module_offers WHERE module_id='release-backup'");
    else $db->prepare("UPDATE goldensite_module_offers SET tier=? WHERE module_id='release-backup'")->execute([$previousTier]);
}
// A delegated administrator may read the Dashboard and write settings, but cannot scan code.
$db->prepare('INSERT INTO goldensite_access_roles (name,permissions_json) VALUES (?,?)')->execute(['Integrity fixture role','["dashboard.read","settings.write"]']);
$roleId=(int)$db->lastInsertId();
$db->prepare("INSERT INTO users (username,email,password,role,role_id,status) VALUES (?,?,?,'user',2,'active')")->execute(['integrity_fixture','integrity@example.test',password_hash($password,PASSWORD_DEFAULT)]);
$limitedId=(int)$db->lastInsertId();
$db->prepare('INSERT INTO goldensite_access_members (user_id,role_id) VALUES (?,?)')->execute([$limitedId,$roleId]);
try {
    installRequest('/logout',['csrf_token'=>installCsrf(installRequest('/admin/dashboard'))]);
    checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'integrity@example.test','password'=>$password])['status']===302,'No se pudo iniciar la sesión delegada.');
    $limitedDashboard=installRequest('/admin/dashboard');
    checkInstall($limitedDashboard['status']===200 && !str_contains($limitedDashboard['body'],'value="check_integrity"'),'La cuenta delegada recibe el formulario reservado.');
    checkInstall(installRequest('/admin/dashboard',['csrf_token'=>installCsrf($limitedDashboard),'action'=>'check_integrity','current_password'=>$password])['status']===422,'La cuenta delegada puede iniciar el análisis.');
    installRequest('/logout',['csrf_token'=>installCsrf(installRequest('/admin/dashboard'))]);
} finally {
    $db->prepare('DELETE FROM goldensite_access_members WHERE user_id=?')->execute([$limitedId]);
    $db->prepare('DELETE FROM goldensite_access_roles WHERE id=?')->execute([$roleId]);
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$limitedId]);
}
echo "OK: $checks comprobaciones de instalación, actualización, firma e integridad.\n";
