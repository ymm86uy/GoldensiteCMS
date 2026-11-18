<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/app/bootstrap.php';

try {
    $command = $argv[1] ?? '';
    if ($command === 'check') {
        $offer = \App\Core\CommercialUpdateChannel::check();
        if ($offer === null) {
            echo "No hay un paquete publicado para esta versión.".PHP_EOL;
        } else {
            echo "Versión disponible: ".$offer['version'].PHP_EOL;
            echo "ZIP oficial: ".$offer['url'].PHP_EOL;
            echo "Firma del ZIP: ".$offer['signature'].PHP_EOL;
        }
    } elseif ($command === 'apply') {
        $result = \App\Core\ReleaseUpdate::apply($argv[2]??'');
        echo "Versión instalada: ".$result['version'].PHP_EOL;
        echo "Respaldo SQL: storage/backups/".$result['database_backup'].PHP_EOL;
        echo "Recuperar código: php .goldensite-release/".$result['rollback']."/recover.php".PHP_EOL;
    } elseif ($command === 'rollback') {
        \App\Core\ReleaseUpdate::rollback($argv[2]??'');
        echo "Código restaurado. El respaldo SQL sigue disponible para recuperación manual.".PHP_EOL;
    } else {
        throw new InvalidArgumentException('Uso: php tools/release-update.php check, apply <ID preparado> o rollback <ID de recuperación>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
