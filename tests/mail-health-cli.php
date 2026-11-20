<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require ($argv[1]??'').'/app/bootstrap.php';
use App\Core\{MailHealth,Settings,SecretStore};
if (($argv[2]??'')==='seed') {
    Settings::writeMany(['smtp_host'=>'smtp.example.test','smtp_port'=>'465','smtp_crypto'=>'ssl','smtp_user'=>'mail-fixture@example.test','smtp_pass'=>SecretStore::seal('MAIL_PRIVATE_QA'),'admin_email'=>'owner@example.test']);
    MailHealth::record('connection',true); MailHealth::record('delivery',true,'A1B2C3D4');
} elseif (($argv[2]??'')==='change') {
    Settings::set('smtp_port','587');
} else throw new RuntimeException('Operación de prueba inválida.');
echo "Estado de prueba preparado sin enviar correo.\n";
