<?php
$runMailFixture=static function (string $action) use ($fixture,$environment): void {
    $process=proc_open([PHP_BINARY,__DIR__.'/mail-health-cli.php',$fixture,$action],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$fixture,$environment,['bypass_shell'=>true]);
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    checkInstall(proc_close($process)===0,'No se preparó el estado de correo. '.$error);
};
$runMailFixture('seed');
$response=installRequest('/admin/site-health?export=json');
$diagnostic=json_decode($response['body'],true,20,JSON_THROW_ON_ERROR);
checkInstall(($diagnostic['mail']['connection']['ok']??false) && ($diagnostic['mail']['delivery']['current']??false)
    && $diagnostic['mail']['delivery']['confirmed_at']===null,'No se distinguen aceptación y recepción pendiente.');
checkInstall(!str_contains($response['body'],'MAIL_PRIVATE_QA') && !str_contains($response['body'],'mail-fixture@example.test')
    && !str_contains($response['body'],'configuration'),'El diagnóstico de correo filtra datos de configuración.');
$page=installRequest('/admin/settings'); $token=installCsrf($page);
$fields=['action'=>'confirm_smtp_receipt','mail_reference'=>'A1B2C3D4','current_password'=>$password];
checkInstall(installRequest('/admin/settings',$fields)['status']===403,'Se confirmó recepción sin CSRF.');
checkInstall(installRequest('/admin/settings',array_replace($fields,['csrf_token'=>$token,'current_password'=>'incorrecta']))['status']===422,'Se confirmó recepción sin contraseña válida.');
checkInstall(installRequest('/admin/settings',array_replace($fields,['csrf_token'=>$token,'mail_reference'=>'00000000']))['status']===422,'Se confirmó una referencia ajena.');
checkInstall(installRequest('/admin/settings',$fields+['csrf_token'=>$token])['status']===303,'No se registra la confirmación manual.');
$diagnostic=json_decode(installRequest('/admin/site-health?export=json')['body'],true,20,JSON_THROW_ON_ERROR);
checkInstall(is_int($diagnostic['mail']['delivery']['confirmed_at']??null),'No se muestra la confirmación histórica.');
$runMailFixture('change');
$diagnostic=json_decode(installRequest('/admin/site-health?export=json')['body'],true,20,JSON_THROW_ON_ERROR);
checkInstall(($diagnostic['mail']['delivery']['current']??true)===false,'Una configuración cambiada conserva indebidamente una prueba vigente.');
checkInstall(installRequest('/admin/settings',$fields+['csrf_token'=>$token])['status']===422,'Se confirmó recepción tras cambiar la configuración.');
echo "Correo: aceptación, confirmación manual, privacidad y cambio de configuración verificados sin envíos.\n";
