<?php

if (!((PHP_VERSION_ID >= 80427 && PHP_VERSION_ID < 80500) ||
    (PHP_VERSION_ID >= 80512 && PHP_VERSION_ID < 80600))) {
    fwrite(STDERR, "Se requiere PHP 8.4.27+ o 8.5.12+. Version detectada: " . PHP_VERSION . PHP_EOL);
    exit(1);
}
