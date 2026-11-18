<?php
namespace App\Core;

final class ModuleCapabilities
{
    public const SCOPE=[
        'ads'=>'Banners de imagen local y anuncios de texto con programación, posición por página y exclusión de cuentas VIP autenticadas.',
        'forum'=>'Foro gratuito con moderación, encuestas, soluciones y avisos. El correo usa el trabajador de módulos y la configuración SMTP.',
        'weather-time'=>'Reloj local y pronóstico con permiso de ubicación. MET Norway ofrece datos sin clave previa aceptación de sus condiciones.',
        'activity-log'=>'Registro, filtros y exportación de actividad. Una solicitud registrada no certifica el éxito de la operación.',
        'gdpr-banner'=>'Preferencias y control de scripts opcionales; el contenido legal debe configurarlo el responsable del sitio.',
        'sitemap'=>'Sitemap de contenido publicado y robots configurables. Comprueba APP_URL antes de publicar.',
        'webhooks'=>'Eventos, cola persistente, firmas JSON y confirmaciones de Discord, Slack y Telegram. Requiere endpoint y trabajador.',
        'asset-optimizer'=>'Elimina comentarios HTML sin alterar scripts, estilos ni texto preformateado. La conversión de imágenes está en Goldensite Digital Asset Management.',
        'qr-generator'=>'Codificación local y descarga de códigos QR. Revisa la lectura del destino antes de imprimir.',
        'import-export'=>'Transferencia de configuración JSON permitida, con validación y exclusión de secretos. Las migraciones de CMS están en Configuración.',
        'blog'=>'Publicación, edición, borradores, listado y búsqueda de artículos; respeta el estado canónico frente a copias antiguas.',
        'edge-waf'=>'Inspección del CMS, reglas manuales por ruta y método, bloqueos por IP o red y análisis manual de archivos.',
        'native-metrics'=>'Medición de visitas HTML sin guardar IP ni agente del visitante. No equivale a analítica de terceros.',
        'monitor-redirects'=>'404 reales y redirecciones internas con detección de ciclos y protección de rutas de acceso.',
        'admin-tools'=>'Limpieza del almacén de caché y mantenimiento de sesiones. No elimina contenido del sitio.',
        'maintenance-mode'=>'Estado público 503, mensaje, imagen validada y acceso administrativo de recuperación.',
        'site-health'=>'Requisitos locales, disponibilidad del catálogo y estado de trabajadores. No es una auditoría externa ni certificación de seguridad.',
        'security-audit'=>'Revisión interna de solo lectura. Informa indicios y comprobaciones directas sobre la instalación y su portada HTTPS.',
        'live-chat'=>'Salas, acceso, moderación, sanciones y mensajes. Módulo independiente de Goldensite Love Dating.',
        'deploy-manager'=>'Versiones y entornos de contenido, publicación y recuperación con respaldo; no administra servidores ni contenedores.',
        'identity-access'=>'Roles, permisos, sesiones, políticas de red y OIDC. El proveedor empresarial requiere configuración y validación propias.',
        'form-builder'=>'Constructor visual, validación, respuestas privadas, exportación y evento de automatización sin incluir las respuestas personales.',
        'login-security'=>'Estilos y marca del acceso. TOTP, contraseña, CSRF y protección básica pertenecen al núcleo.',
        'marketplace'=>'Gestión de vendedores, productos, inventario y reservas. Los cobros reales y la conciliación requieren integrar una pasarela.',
        'server-downloads'=>'Biblioteca privada con colecciones, versiones, permisos por cuenta, enlaces revocables con caducidad y límite de accesos, integridad SHA-256 y registro de entregas autorizadas. Almacenar un ZIP no instala su código.',
        'gallery'=>'Colecciones, imágenes y publicación mediante shortcode. Verifica los permisos sobre medios externos.',
        'universal-importer'=>'Importación transaccional de artículos compatibles; no reproduce automáticamente temas, medios o extensiones de otros CMS.',
        'newsletter'=>'Alta con doble confirmación, bajas firmadas, borradores, programación, cola privada y resultados SMTP. Requiere SMTP y trabajador.',
        'digital-vault'=>'Protección de bloques y exclusión en buscador. Los derechos VIP requieren una fuente de autorización válida.',
        'commerce-sales'=>'Productos, catálogo y gestión de pedidos existentes. Los cobros reales requieren integración de pasarela.',
        'seo-analytics'=>'Metadatos y scripts opcionales con consentimiento. Modificar scripts requiere contraseña actual.',
        'dam'=>'Biblioteca y derivados WebP reales con GD; conserva originales. No incorpora indexación EXIF ni conversión AVIF.',
        'db-cleaner'=>'Limpieza y optimización limitadas. Genera un respaldo antes de cualquier operación destructiva.',
        'automation-hub'=>'Condiciones, registro local y acciones externas en cola, demora de hasta siete días y cancelación al retirar una regla.',
        'sso'=>'SAML 2.0 con respuesta y aserción firmadas. SCIM 2.0 para usuarios aprovisionados. Requiere un proveedor corporativo y una configuración HTTPS.',
        'booking'=>'Horarios por profesional, reservas autenticadas y cancelación de citas futuras. Reutiliza las citas existentes.',
        'support-desk'=>'Convierte las consultas del buzón en tickets con prioridad, responsable, notas internas e historial. Reutiliza el mismo formulario y las respuestas del buzón.',
        'academy'=>'Cursos con evaluación opcional por lección y certificado tras completar el programa.',
        'events'=>'Eventos de acceso por reserva, con aforo y QR validado por personal autorizado.',
        's3-connector'=>'Copia a un bucket de Amazon S3 con cifrado del lado de Goldensite y restaura si falta el archivo local. Requiere un bucket privado y credenciales de alcance limitado.',
        'release-backup'=>'Prepara ZIP firmados con versión de origen y destino. La consola respalda SQL y código, instala cambios aditivos y conserva un punto de recuperación.',
        'dating'=>'Alta explícita de mayores de edad, confirmación de correo, moderación de perfiles y fotos privadas, coincidencias mutuas, mensajes, bloqueo, denuncias e historias. Los pagos reales requieren pasarela; los créditos virtuales no son dinero depositado.',
    ];
    public static function report(): array
    {
        $rows=[]; $db=Database::getInstance();
        foreach (ModuleCatalog::all() as $module) {
            $requirements=[];
            if ($module['id']==='newsletter'&&Settings::get('smtp_host')==='') $requirements[]='Configurar SMTP y verificar remitente y proveedor.';
            if ($module['id']==='webhooks'&&Settings::get('hooks_url')==='') $requirements[]='Guardar un endpoint HTTPS y seleccionar eventos.';
            if (in_array($module['id'],['newsletter','automation-hub'],true)&&time()-(int)Settings::get('module_worker_seen','0')>300) $requirements[]='Programar o revisar el trabajador de módulos.';
            if ($module['id']==='webhooks'&&time()-(int)Settings::get('webhook_worker_seen','0')>300) $requirements[]='Programar o revisar el trabajador de webhooks.';
            if ($module['id']==='identity-access'&&Settings::get('oidc_enabled')!=='1') $requirements[]='OIDC opcional sin configurar; los roles locales siguen disponibles.';
            if ($module['id']==='dam'&&(!extension_loaded('gd')||!function_exists('imagewebp'))) $requirements[]='Instalar GD con soporte WebP.';
            if (in_array($module['id'],['marketplace','commerce-sales','dating'],true)) $requirements[]='Hay capacidades pendientes de integración; consulta el alcance actual.';
            $rows[]=['id'=>$module['id'],'name'=>$module['name'],'tier'=>ModuleCatalog::requiresLicenseHere($module)?'PRO':'FREE','enabled'=>FrontendEngine::isModuleActive($module['id']),'scope'=>self::SCOPE[$module['id']]??$module['desc'],'requirements'=>$requirements];
        }
        return $rows;
    }
}
