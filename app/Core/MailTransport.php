<?php
namespace App\Core;
use PHPMailer\PHPMailer\{PHPMailer,SMTP};
final class MailTransport
{
    public static function probe(): void
    {
        $host=Settings::get('smtp_host'); $port=(int)Settings::get('smtp_port','587'); $crypto=Settings::get('smtp_crypto','tls');
        if (!$host || !filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) throw new \RuntimeException('Guarda primero un servidor SMTP válido.');
        $records=dns_get_record($host,DNS_A|DNS_AAAA); $addresses=[];
        foreach ($records?:[] as $record) { $ip=$record['ip']??$record['ipv6']??null; if (!$ip) continue; if (!SafeHttp::isPublicIp($ip)) throw new \RuntimeException('La comprobación remota solo admite proveedores con direcciones públicas.'); $addresses[]=$ip; }
        if (!$addresses) throw new \RuntimeException('El servidor SMTP no tiene una dirección pública resoluble.');
        $ip=str_contains($addresses[0],':')?'['.$addresses[0].']':$addresses[0];
        $smtp=new SMTP(); $smtp->Timeout=5; $smtp->Timelimit=8;
        $options=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true,'SNI_server_name'=>$host]];
        try {
            if (!$smtp->connect(($crypto==='ssl'?'ssl://':'').$ip,$port,5,$options) || !$smtp->hello('goldensite.local')) throw new \RuntimeException('No se recibió una respuesta SMTP válida.');
            if ($crypto==='tls' && (!$smtp->startTLS() || !$smtp->hello('goldensite.local'))) throw new \RuntimeException('El servidor no completó STARTTLS con verificación de certificado.');
        } finally { if ($smtp->connected()) $smtp->quit(true); $smtp->close(); }
    }
    public static function send(string $to,string $subject,string $body): bool
    {
        if (!filter_var($to,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$subject) || Settings::get('smtp_host')==='') return false;
        try {
            $mail=new PHPMailer(true); $mail->isSMTP(); $mail->Host=Settings::get('smtp_host'); $mail->Port=(int)Settings::get('smtp_port','587');
            $mail->SMTPSecure=Settings::get('smtp_crypto','tls'); $mail->SMTPAuth=Settings::get('smtp_user')!==''; $mail->Username=Settings::get('smtp_user'); $mail->Password=SecretStore::open(Settings::get('smtp_pass'));
            $mail->Timeout=10; $mail->CharSet='UTF-8'; $mail->SMTPDebug=0;
            $administrative=Settings::get('admin_email');
            $from=filter_var($mail->Username,FILTER_VALIDATE_EMAIL)?$mail->Username:$administrative;
            if (!filter_var($from,FILTER_VALIDATE_EMAIL)) return false;
            $mail->setFrom($from,Settings::get('site_title','GoldenSiteCMS')); $mail->addAddress($to); $mail->Subject=$subject; $mail->Body=$body; $mail->isHTML(false);
            return $mail->send();
        } catch (\Throwable $e) { error_log('SMTP delivery failed; inspect provider connectivity and configuration.'); return false; }
    }
}
