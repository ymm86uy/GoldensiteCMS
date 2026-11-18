<?php
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
if ($argc!==4) {
    fwrite(STDERR,"Uso: php tools/migrate-joomla-catalog.php sitio.sql jdownloads.sql prefijo_\n");
    exit(1);
}
foreach (\App\Core\JoomlaCatalogImport::import([$argv[1],$argv[2]],$argv[3]) as $type=>$count) echo $type,': ',$count,PHP_EOL;
