<?php
$edition=trim(file_get_contents($fixture.'/release-edition.txt'));
$owner=$edition==='owner';
checkInstall(installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'owner@example.test','password'=>$password])['status']===302,'No se pudo iniciar sesión de prueba.');
$catalog=installRequest('/admin/plugins');
$token=installCsrf($catalog);
checkInstall(str_contains($catalog['body'],'data-id="deploy-manager"')===$owner,'El catálogo muestra Deploy Manager en la edición incorrecta.');
checkInstall(str_contains(installRequest('/admin/dashboard')['body'],'href="/admin/deploy-manager"')===$owner,'La navegación muestra Deploy Manager en la edición incorrecta.');
$before=(int)$db->query('SELECT COUNT(*) FROM goldensite_deploy_releases')->fetchColumn();
checkInstall(installRequest('/admin/deploy-manager')['status']===($owner?200:404),'Acceso GET a Deploy Manager incorrecto.');
$stage=installRequest('/admin/deploy-manager',['csrf_token'=>$token,'action'=>'stage','label'=>'Comprobación de edición']);
checkInstall($stage['status']===($owner?302:404),'Acceso POST a Deploy Manager incorrecto.');
$after=(int)$db->query('SELECT COUNT(*) FROM goldensite_deploy_releases')->fetchColumn();
checkInstall($after===$before+($owner?1:0),'La restricción alteró versiones de la edición incorrecta.');
if (!$owner) {
    checkInstall(str_contains($catalog['body'],'Buscar actualizaciones del CMS') && str_contains($catalog['body'],'https://goldensitecms.com/updates/commercial/')
        && str_contains($catalog['body'],'https://goldensitecms.com/extension-catalog/'),'Faltan los canales oficiales en Comercial.');
}
echo 'Restricciones Owner/Comercial y canales oficiales verificados ('.$edition.').'.PHP_EOL;
