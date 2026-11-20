<?php
$auditRoute='/admin/security-audit';
checkInstall(installRequest($auditRoute)['status']===302,'La auditoría se abrió sin sesión.');
checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'owner@example.test','password'=>$password])['status']===302,'Falló el acceso de administrador.');
$catalog=installRequest('/admin/plugins');
$token=installCsrf($catalog);
$owner=trim(file_get_contents($fixture.'/release-edition.txt'))==='owner';
checkInstall(installRequest($auditRoute)['status']===403,'La auditoría está disponible antes de activarse.');
if ($owner) {
    checkInstall(installRequest('/admin/plugins',['csrf_token'=>$token,'action_toggle_module'=>'1','toggle_state'=>'1','module_id'=>'security-audit'])['status']===200,'Owner no pudo activar la auditoría incluida.');
} else {
    checkInstall(installRequest('/admin/plugins',['csrf_token'=>$token,'action_toggle_module'=>'1','toggle_state'=>'1','module_id'=>'security-audit'])['status']===403,'Comercial activó el módulo sin licencia.');
    $options=['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1'];
    $config=dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($config)) $options['config']=$config;
    $key=openssl_pkey_new($options);
    checkInstall($key && openssl_pkey_export($key,$private,null,$options),'No se creó una identidad temporal.');
    $policy=$fixture.'/config/catalog-policy.nfx';
    if (is_file($policy)) {
        $signed=explode('.',trim(file_get_contents($policy)))[1];
        $claims=json_decode(base64_decode(strtr($signed,'-_','+/')),true,16,JSON_THROW_ON_ERROR);
        file_put_contents($policy,App\Core\LicenseCode::sign($claims,$private,'NFXC1'));
    }
    file_put_contents($fixture.'/config/license-public.pem',openssl_pkey_get_details($key)['key']);
    $license=App\Core\LicenseCode::sign(['v'=>1,'id'=>bin2hex(random_bytes(16)),'m'=>'security-audit','d'=>'127.0.0.1','iat'=>time()-1,'exp'=>time()+3600],$private);
    checkInstall(installRequest('/admin/plugins',['csrf_token'=>$token,'action_save_license'=>'1','license_key'=>$license,'module_id'=>'security-audit'])['status']===200,'Comercial no pudo activar la licencia válida.');
}
$page=installRequest($auditRoute);
checkInstall($page['status']===200 && str_contains($page['body'],'Ejecutar auditor'), 'No aparece el módulo activado.');
checkInstall(installRequest($auditRoute,[])['status']===403,'Se aceptó ejecutar sin CSRF.');
$file=$fixture.'/public/audit-fixture.bak';
file_put_contents($file,'AUDIT_PRIVATE_MARKER_QA');
$data=['edition'=>$owner?'owner':'commercial','version'=>trim(file_get_contents($fixture.'/release-version.txt')),'files'=>['routes/modules.php'=>str_repeat('0',64)]];
file_put_contents($fixture.'/storage/audit-release-manifest.json',json_encode($data,JSON_THROW_ON_ERROR));
$scan=installRequest($auditRoute,['csrf_token'=>installCsrf($page)]);
$body=html_entity_decode($scan['body'],ENT_QUOTES|ENT_HTML5,'UTF-8');
checkInstall($scan['status']===200 && str_contains($body,'public/audit-fixture.bak') && str_contains($body,'Administrador sin segundo factor')
    && str_contains($body,'Diferencia frente al paquete de referencia') && !str_contains($body,'AUDIT_PRIVATE_MARKER_QA'),'El informe omitió hallazgos esperados o reveló el archivo.');
checkInstall(installRequest($auditRoute,['csrf_token'=>installCsrf($page)])['status']===429,'La frecuencia de auditoría no quedó limitada.');
$store=new Modules\GoldensiteSecurityAudit\AuditHistory($db);
$entries=$store->recent();
checkInstall(count($entries)===1 && $entries[0]['cms_version']===trim(file_get_contents($fixture.'/release-version.txt')),'No se guardó la versión de la auditoría.');
$firstId=(int)$entries[0]['id'];
$stored=installRequest($auditRoute.'?audit='.$firstId);
checkInstall($stored['status']===200 && str_contains($stored['body'],'Historial de auditorías')
    && str_contains($stored['body'],'referencia inicial') && !str_contains($stored['body'],'AUDIT_PRIVATE_MARKER_QA'),'No se recuperó el informe privado esperado.');
checkInstall(count($store->recent())===1,'Abrir un informe ejecutó otra auditoría.');
for ($i=0;$i<31;$i++) $last=$store->save(['findings'=>[],'limits'=>[],'completed'=>[],'coverage'=>[]],(int)$admin[0]['id'],'1.0.35',$owner?'owner':'commercial');
checkInstall(count($store->recent())===30 && $store->find($firstId)===null,'La retención no conserva exactamente los últimos 30 informes.');
checkInstall(installRequest($auditRoute.'?audit='.$firstId)['status']===404,'Un informe retirado sigue disponible.');
checkInstall(installRequest($auditRoute.'?audit[]=1')['status']===404,'Se aceptó un identificador de informe no escalar.');
$delegatedPassword=bin2hex(random_bytes(16));
$db->prepare("INSERT INTO users (username,email,password,role,role_id,status) VALUES (?, ?, ?, 'user', 2, 'active')")->execute(['auditor_fixture','auditor@example.test',password_hash($delegatedPassword,PASSWORD_DEFAULT)]);
$delegated=(int)$db->lastInsertId();
$db->exec("INSERT INTO goldensite_access_roles (name,permissions_json) VALUES ('Operador de prueba','[\"dashboard.read\",\"security.read\"]')");
$role=(int)$db->lastInsertId();
$db->prepare('INSERT INTO goldensite_access_members (user_id,role_id) VALUES (?,?)')->execute([$delegated,$role]);
$cookie=$fixture.'/storage/audit-delegate.cookie';
checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'auditor@example.test','password'=>$delegatedPassword])['status']===302,'No se inició sesión delegada.');
checkInstall(installRequest($auditRoute)['status']===403,'Una cuenta delegada abrió la auditoría.');
checkInstall(installRequest($auditRoute.'?audit='.$last['id'])['status']===403,'Una cuenta delegada abrió un informe histórico.');
echo "Auditoría interna: licencia, permisos, CSRF, límites e informe verificados.\n";
