<?php
$response=installRequest('/admin/site-health?export=json');
checkInstall($response['status']===200,'No se exporta el diagnóstico del alojamiento.');
$diagnostic=json_decode($response['body'],true,20,JSON_THROW_ON_ERROR);
$layers=array_column($diagnostic['hosting']['checks']??[],null,'id');
checkInstall(count($layers)===9 && !empty($diagnostic['hosting']['checked_at']),'Faltan capas o fecha de comprobación.');
checkInstall($layers['public-root']['state']==='checked','No se reconoce la raíz pública de la instalación de prueba.');
checkInstall($layers['transport']['state']==='unknown','HTTP local se presentó como HTTPS comprobado.');
checkInstall($layers['server-waf']['state']==='unknown','Se declaró verificada la protección del proveedor.');
checkInstall($layers['clamav-engine']['state']==='unknown' && $layers['clamav-signatures']['state']==='unknown','Se declaró comprobado un antivirus no configurado.');
$spoofed=installRequest('/admin/site-health?export=json',null,['X-Forwarded-Proto: https','X-Mod-Security: enabled']);
$spoofed=json_decode($spoofed['body'],true,20,JSON_THROW_ON_ERROR); $spoofed=array_column($spoofed['hosting']['checks'],null,'id');
checkInstall($spoofed['transport']['state']==='unknown' && $spoofed['server-waf']['state']==='unknown','Una cabecera del visitante alteró el diagnóstico.');
$page=installRequest('/admin/site-health');
checkInstall(str_contains($page['body'],'Capas del alojamiento') && str_contains($page['body'],'data-hosting-check="server-waf"'),'No se muestran las capas en Site Doctor.');
checkInstall(!str_contains($response['body'],str_replace('\\','/',$fixture)),'El informe expone la ruta privada absoluta.');
echo "Site Doctor: capas locales, estados sin comprobar y resistencia a cabeceras falsas verificados.\n";
