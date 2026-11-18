<?php
namespace App\Core;
final class SiteConfiguration
{
    public static function maintenanceEnabled(): bool
    {
        return Settings::get('maintenance_enabled','0')==='1' || Settings::get('maintenance_mode','0')==='1';
    }

    public static function validate(array $input): array
    {
        $values=[];
        $limits=['site_title'=>150,'site_description'=>2000,'admin_email'=>150,'admin_custom_slug'=>100,'site_timezone'=>80,'site_language'=>8,'maintenance_mode'=>1,'maintenance_message'=>1000,'header_scripts'=>24000,'footer_scripts'=>24000,'smtp_host'=>253,'smtp_port'=>5,'smtp_user'=>200,'smtp_crypto'=>8,'enable_security_headers'=>1,'backup_schedule_freq'=>10];
        foreach ($limits as $key=>$max) {
            if (!array_key_exists($key,$input)) continue;
            $value=$input[$key];
            if (!is_string($value) || !mb_check_encoding($value,'UTF-8')
                || ($key==='maintenance_message'?mb_strlen($value):strlen($value))>$max || str_contains($value,"\0")) throw new \InvalidArgumentException('Campo inválido: '.$key);
            $value=trim($value);
            if (in_array($key,['site_title','site_description','maintenance_message'],true) && preg_match('/[<>]/',$value)) throw new \InvalidArgumentException('Introduce texto sin etiquetas en '.$key.'.');
            if ($key==='site_title' && $value==='') throw new \InvalidArgumentException('El nombre del sitio es obligatorio.');
            if ($key==='admin_email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Correo administrativo inválido.');
            if ($key==='site_timezone' && !in_array($value,timezone_identifiers_list(),true)) throw new \InvalidArgumentException('Zona horaria desconocida.');
            if ($key==='site_language' && !in_array($value,['es','en','pt'],true)) throw new \InvalidArgumentException('Idioma de contenido inválido.');
            if (in_array($key,['maintenance_mode','enable_security_headers'],true) && !in_array($value,['0','1'],true)) throw new \InvalidArgumentException('Estado de configuración inválido.');
            if ($key==='maintenance_message' && $value==='') $value='Estamos realizando mejoras. Volveremos pronto.';
            if ($key==='smtp_host' && $value!=='' && (!filter_var($value,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME) || preg_match('/[;:\s\\\\\/]/',$value))) throw new \InvalidArgumentException('Usa un nombre de servidor SMTP sin protocolo ni rutas.');
            if ($key==='smtp_port' && (!ctype_digit($value) || (int)$value<1 || (int)$value>65535)) throw new \InvalidArgumentException('Puerto SMTP inválido.');
            if ($key==='smtp_crypto' && !in_array($value,['tls','ssl'],true)) throw new \InvalidArgumentException('Selecciona STARTTLS o TLS implícito.');
            if ($key==='backup_schedule_freq' && !in_array($value,['off','daily','weekly'],true)) throw new \InvalidArgumentException('Frecuencia de respaldo inválida.');
            if ($key==='admin_custom_slug') {
                $value=AdminPath::fromLabel($value);
                if (!AdminPath::valid($value)) throw new \InvalidArgumentException('Usa de 3 a 31 caracteres. Empieza con una letra y usa letras, números, guiones o guiones bajos.');
                if ($value!=='admin' && (Router::getInstance()->hasRouteRoot($value)
                    || Database::getInstance()->fetch('SELECT id FROM goldensite_pages WHERE slug = ? UNION ALL SELECT id FROM goldensite_posts WHERE slug = ? UNION ALL SELECT id FROM posts WHERE slug = ? LIMIT 1',[$value,$value,$value]))) {
                    throw new \InvalidArgumentException('La ruta ya está reservada o coincide con contenido existente.');
                }
            }
            $values[$key]=$value;
        }
        if (isset($values['maintenance_mode'])) $values['maintenance_enabled']=$values['maintenance_mode'];
        $secret=$input['smtp_pass']??'';
        if (!is_string($secret) || strlen($secret)>4096 || preg_match('/[\x00\r\n]/',$secret)) throw new \InvalidArgumentException('Credencial SMTP inválida.');
        if ($secret!=='') $values['smtp_pass']=SecretStore::seal($secret);
        if (($input['clear_smtp_pass']??'')==='1') $values['smtp_pass']='';
        if ($secret==='' && !isset($values['smtp_pass']) && Settings::get('smtp_pass')!=='') {
            foreach (['smtp_host','smtp_user','smtp_crypto'] as $key) if (isset($values[$key]) && $values[$key]!==Settings::get($key)) throw new \InvalidArgumentException('Al cambiar el proveedor de correo, introduce nuevamente la contraseña o elimina la credencial guardada.');
        }
        return $values;
    }
}
