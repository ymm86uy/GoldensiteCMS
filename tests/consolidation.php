<?php
// Runs in tests/run.php without production configuration or an external service.
$declarations=$directory.'/declarations';
mkdir($declarations.'/modules',0700,true);
file_put_contents($declarations.'/modules/first.php',"<?php namespace Sample; class Thing {} interface Contract {} trait Shared {} enum State {} \$name=Thing::class; \$anonymous=new class {}; ");
verify(!App\Core\ReleaseIntegrity::duplicateClasses($declarations),'El inventario distingue clases, ::class y clases anónimas');
file_put_contents($declarations.'/modules/second.php',"<?php namespace sample; class thing {} interface contract {} trait shared {} enum state {} ");
$collisions=App\Core\ReleaseIntegrity::duplicateClasses($declarations);
verify(count($collisions)===4 && count($collisions['sample\\thing'])===2,'Se rechazan clases, interfaces, traits y enums repetidos sin distinguir mayúsculas');
foreach (['modules/goldensite-gdpr-banner/GdprModule.php','plugins/goldensite-chat/index.php','plugins/goldensite-chat/ChatEngine.php','plugins/goldensite-newsletter/MailerEngine.php'] as $adapter) {
    require $root.'/'.$adapter;
    require $root.'/'.$adapter;
}
verify(defined(Modules\GoldensiteGdprBanner\GdprModule::class.'::POLICY'),'La entrada histórica de cookies conserva la implementación actual');
verify(str_contains(str_replace('\\','/',(new ReflectionClass(Plugins\GoldensiteChat\ChatEngine::class))->getFileName()),'/modules/GoldensiteChat/'),'El chat histórico utiliza el único motor nativo');
verify(str_contains(str_replace('\\','/',(new ReflectionClass(Plugins\GoldensiteNewsletter\MailerEngine::class))->getFileName()),'/modules/GoldensiteNewsletter/'),'El boletín histórico utiliza el único motor nativo');
verify(!App\Core\ReleaseIntegrity::duplicateClasses($root),'El producto no contiene clases propias duplicadas');
verify(App\Core\ProductEdition::ownerOnlyPath('docs/CANAL-OWNER-1.0.25.md') && App\Core\ProductEdition::ownerOnlyPath('deployment/commercial-channel-index.php'),'Las instrucciones y publicación de Owner se excluyen de Comercial');
$running=$directory.'/running-router.php';
file_put_contents($running,'<?php // unchanged router');
$handle=fopen($running,'rb');
try {
    (new ReflectionMethod(App\Core\ReleaseUpdate::class,'write'))->invoke(null,$running,'<?php // unchanged router');
    verify(file_get_contents($running)==='<?php // unchanged router','No se reemplaza un archivo abierto cuyo contenido ya es correcto');
} finally { fclose($handle);unlink($running); }
unlink($declarations.'/modules/first.php');unlink($declarations.'/modules/second.php');
rmdir($declarations.'/modules');rmdir($declarations);
