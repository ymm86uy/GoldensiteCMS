<?php
namespace App\Core;
final class ModuleCatalog
{
    public const FREE = ['activity-log', 'gdpr-banner', 'sitemap', 'webhooks', 'asset-optimizer', 'qr-generator', 'import-export', 'blog', 'edge-waf', 'native-metrics', 'monitor-redirects', 'admin-tools', 'maintenance-mode', 'site-health', 'live-chat', 'deploy-manager', 'identity-access', 'form-builder', 'booking', 'support-desk', 'academy', 'events', 's3-connector', 'weather-time'];
    public static function canonical(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/^'.LegacyFormat::BRAND_PATTERN.'-/', '', $id);
        return ['downloads'=>'server-downloads','goldensitemarketplace'=>'marketplace',LegacyFormat::PREFIX.'marketplace'=>'marketplace','goldensitelove-dating'=>'dating',LegacyFormat::PREFIX.'love-dating'=>'dating','paywall'=>'digital-vault','sales'=>'commerce-sales','seo-tracker'=>'seo-analytics','seo'=>'seo-analytics','database-cleaner'=>'db-cleaner','sso-scim'=>'sso','migrator'=>'import-export','metrics'=>'native-metrics','redirects'=>'monitor-redirects','site-doctor'=>'site-health','chat'=>'live-chat'][$id] ?? $id;
    }
    public static function isPro(string $id): bool { return (bool)(self::get($id)['is_pro'] ?? false); }
    public static function issuable(string $id): bool { return self::isPro($id); }
    public static function requiresLicenseHere(array $module): bool
    {
        return $module['is_pro'] && !(($module['included_owner']??false) && ProductEdition::current()==='owner');
    }
    public static function get(string $id): ?array
    {
        $id = self::canonical($id);
        foreach (self::all() as $module) if ($module['id'] === $id) return $module;
        return null;
    }
    public static function prices(): array
    {
        $prices = [];
        $snapshot = dirname(__DIR__, 2) . '/config/module-prices.json';
        if (is_file($snapshot)) {
            $data = json_decode(file_get_contents($snapshot), true);
            foreach (is_array($data) ? $data : [] as $id => $price) if (self::isPro((string)$id) && is_string($price) && strlen($price) <= 50) $prices[self::canonical($id)] = $price;
        }
        foreach (Database::getInstance()->fetchAll('SELECT module_id, price_text FROM goldensite_module_prices') as $row) $prices[self::canonical($row['module_id'])] = $row['price_text'];
        return $prices;
    }
    public static function exportPrices(): array
    {
        $prices = self::prices(); $result = [];
        foreach (self::all() as $module) if ($module['is_pro']) $result[$module['id']] = self::price($module['id'], $prices);
        return $result;
    }
    public static function price(string $id, ?array $prices = null): string
    {
        $module = self::get($id);
        if (!$module||!$module['is_pro']) return 'Incluido';
        $fallback=($prices ?? self::prices())[$module['id']]??($module['price_def']??'Consultar precio');
        return ModuleCommerce::pricing($module['id'],$fallback)['price'];
    }
    public static function definitions(): array
    {
        static $modules = null;
        $modules ??= [
            ['id'=>'ads','name'=>'Goldensite ADS','is_pro'=>false,'url'=>'/admin/ads','desc'=>'Campañas patrocinadas con programación, posiciones, rotación por prioridad y navegación sin anuncios para cuentas VIP.'],
            ['id'=>'weather-time','name'=>'Goldensite Weather & Time','is_pro'=>false,'url'=>'/admin/weather-time','desc'=>'Reloj local y clima por ubicación aproximada, con permiso del visitante y posición configurable.'],
            ['id'=>'deploy-manager','name'=>'Goldensite Deploy Manager','is_pro'=>false,'url'=>'/admin/deploy-manager','desc'=>'Versiones de contenido en prueba y producción, publicación y recuperación con respaldo automático.'],
            ['id'=>'identity-access','name'=>'Goldensite Identity & Access','is_pro'=>false,'url'=>'/admin/identity-access','desc'=>'Roles, permisos por operación, políticas de red y autenticación empresarial OIDC con proveedor.'],
            ['id'=>'form-builder','name'=>'Goldensite Form Builder','is_pro'=>false,'url'=>'/admin/form-builder','desc'=>'Constructor visual de formularios, encuestas y registros de respuestas con exportación privada.'],
            ['id'=>'booking','name'=>'Goldensite Booking & Appointments','is_pro'=>false,'url'=>'/admin/booking','desc'=>'Disponibilidad por profesional, citas y cancelaciones con control de solapamientos.'],
            ['id'=>'support-desk','name'=>'Goldensite Support Desk','is_pro'=>false,'url'=>'/admin/support-desk','desc'=>'Seguimiento de consultas del buzón con responsables, prioridades, estados e historial.'],
            ['id'=>'academy','name'=>'Goldensite Academy','is_pro'=>false,'url'=>'/admin/academy','desc'=>'Cursos, lecciones, evaluaciones, progreso y certificados verificables.'],
            ['id'=>'events','name'=>'Goldensite Events & Tickets','is_pro'=>false,'url'=>'/admin/events','desc'=>'Eventos con aforo, entradas por cuenta y validación QR de un solo uso.'],
            ['id'=>'s3-connector','name'=>'Goldensite S3 Connector','is_pro'=>false,'url'=>'/admin/s3-connector','desc'=>'Copias remotas cifradas y restauración verificada para Downloads y DAM.'],
            // Core and free extensions
            ['id' => 'activity-log', 'name' => 'Goldensite Activity & Audit Log', 'is_pro' => false, 'url' => '/admin/activity-log', 'desc' => 'Rastreo e inspección de eventos de seguridad.'],
            ['id' => 'gdpr-banner', 'name' => 'Goldensite GDPR & Cookie Banner', 'is_pro' => false, 'url' => '/admin/gdpr-banner', 'desc' => 'Aviso flotante legal de consentimiento de cookies.'],
            ['id' => 'sitemap', 'name' => 'Goldensite Sitemap & Robots SEO', 'is_pro' => false, 'url' => '/admin/sitemap', 'desc' => 'Generador dinámico de mapa del sitio XML y robots.txt.'],
            ['id' => 'webhooks', 'name' => 'Goldensite Webhook Dispatcher', 'is_pro' => false, 'url' => '/admin/webhooks', 'desc' => 'Cola de eventos, entrega HTTPS y reintentos para Discord, Slack, Telegram y JSON firmado.'],
            ['id' => 'asset-optimizer', 'name' => 'Goldensite Asset Optimizer', 'is_pro' => false, 'url' => '/admin/asset-optimizer', 'desc' => 'Eliminación segura de comentarios HTML; conserva scripts y texto preformateado.'],
            ['id' => 'qr-generator', 'name' => 'Goldensite Basic QR Generator', 'is_pro' => false, 'url' => '/admin/qr-generator', 'desc' => 'Generación de códigos QR en tiempo real.'],
            ['id' => 'import-export', 'name' => 'Goldensite Import / Export', 'is_pro' => false, 'url' => '/admin/import-export', 'desc' => 'Herramienta nativa para importar/exportar ajustes.'],
            ['id' => 'blog', 'name' => 'Goldensite Blog', 'is_pro' => false, 'url' => '/admin/posts', 'desc' => 'Sistema de gestión de entradas y artículos.'],
            ['id' => 'edge-waf', 'name' => 'Goldensite Edge WAF & Request Guard', 'is_pro' => false, 'url' => '/admin/waf', 'desc' => 'Cortafuegos del núcleo con reglas de acceso, registro de bloqueos y análisis manual de archivos.'],
            ['id' => 'native-metrics', 'name' => 'Goldensite Native Metrics', 'is_pro' => false, 'url' => '/admin/analytics', 'desc' => 'Medición interna de tráfico en tiempo real.'],
            ['id' => 'monitor-redirects', 'name' => 'Goldensite 404 Monitor & Redirects', 'is_pro' => false, 'url' => '/admin/monitor-redirects', 'desc' => 'Detección de errores y reglas de redirección.'],
            ['id' => 'admin-tools', 'name' => 'Goldensite Admin Tools', 'is_pro' => false, 'url' => '/admin/admin-tools', 'desc' => 'Kit de herramientas administrativas para limpieza.'],
            ['id' => 'maintenance-mode', 'name' => 'Goldensite Maintenance Mode', 'is_pro' => false, 'url' => '/admin/maintenance', 'desc' => 'Modo mantenimiento personalizable con un clic.'],
            ['id' => 'site-health', 'name' => 'Goldensite Site Health & Doctor', 'is_pro' => false, 'url' => '/admin/site-health', 'desc' => 'Auditoría de salud del sitio en tiempo real.'],
            ['id'=>'security-audit','name'=>'Goldensite Security Audit','is_pro'=>true,'included_owner'=>true,'url'=>'/admin/security-audit','desc'=>'Auditoría interna de archivos, integridad, PHP, cuentas y cabeceras HTTPS.'],
            ['id' => 'live-chat', 'name' => 'Goldensite Live Chat & Engagement', 'is_pro' => false, 'url' => '/admin/live-chat', 'desc' => 'Chat en vivo profesional e interactivo.'],

            // 15 PRO
            ['id' => 'login-security', 'name' => 'Goldensite Custom Login PRO', 'is_pro' => true, 'price_def' => 'Consultar precio', 'url' => '/admin/login-security', 'desc' => 'Plantillas de acceso con animación, vistas previas, identidad de marca e imágenes de fondo.'],
            ['id' => 'marketplace', 'name' => 'Goldensite Marketplace PRO', 'is_pro' => true, 'price_def' => '$24.99/mes', 'url' => '/admin/marketplace', 'desc' => 'Catálogo multivendedor, gestión de inventario y reservas sin pago en línea.'],
            ['id' => 'server-downloads', 'name' => 'Goldensite Storage & Downloads PRO', 'is_pro' => true, 'price_def' => '$19.99/mes', 'url' => '/admin/downloads', 'desc' => 'Archivos privados, versiones, permisos por cuenta y enlaces revocables con caducidad y límite de accesos.'],
            ['id' => 'gallery', 'name' => 'Goldensite Gallery & Motion FX PRO', 'is_pro' => true, 'price_def' => '$15.00/mes', 'url' => '/admin/gallery', 'desc' => 'Galerías multimedia interactivas con efectos FX.'],
            ['id' => 'universal-importer', 'name' => 'Goldensite Universal Importer PRO', 'is_pro' => true, 'price_def' => '$29.99/mes', 'url' => '/admin/importer', 'desc' => 'Importación de contenidos; revisa compatibilidad y URLs antes de migrar.'],
            ['id' => 'newsletter', 'name' => 'Goldensite Newsletter PRO', 'is_pro' => true, 'price_def' => '$12.99/mes', 'url' => '/admin/newsletter', 'desc' => 'Doble confirmación, bajas seguras, campañas programadas y cola SMTP con resultados reales.'],
            ['id' => 'digital-vault', 'name' => 'Goldensite Digital Vault & Paywall PRO', 'is_pro' => true, 'price_def' => '$14.99/mes', 'url' => '/admin/paywall', 'desc' => 'Bloqueo de contenido exclusivo con HMAC.'],
            ['id' => 'commerce-sales', 'name' => 'Goldensite Commerce & Sales PRO', 'is_pro' => true, 'price_def' => '$24.99/mes', 'url' => '/admin/commerce', 'desc' => 'Módulo de ventas y catálogo digital.'],
            ['id' => 'seo-analytics', 'name' => 'Goldensite SEO & Analytics PRO', 'is_pro' => true, 'price_def' => '$9.99/mes', 'url' => '/admin/seo-tracker', 'desc' => 'Inyección de etiquetas meta dinámicas y scripts.'],
            ['id' => 'dam', 'name' => 'Goldensite Digital Asset Management PRO', 'is_pro' => true, 'price_def' => '$45.00', 'url' => '/admin/dam', 'desc' => 'Gestión avanzada de activos digitales.'],
            ['id' => 'db-cleaner', 'name' => 'Goldensite Database Cleaner PRO', 'is_pro' => true, 'price_def' => '$40.00', 'url' => '/admin/db-cleaner', 'desc' => 'Depuración y optimización de base de datos.'],
            ['id' => 'automation-hub', 'name' => 'Goldensite Automation Hub PRO', 'is_pro' => true, 'price_def' => '$38.99/mes', 'url' => '/admin/automation-hub', 'desc' => 'Reglas con condiciones, eventos, demoras y acciones en cola; requiere trabajador para envíos externos.'],
            ['id' => 'sso', 'name' => 'Goldensite SSO & SCIM Enterprise PRO', 'is_pro' => true, 'price_def' => '$80.00', 'url' => '/admin/sso', 'desc' => 'Acceso corporativo por SAML 2.0 y aprovisionamiento de cuentas con SCIM 2.0.'],
            ['id' => 'release-backup', 'name' => 'Goldensite Release & Backup PRO', 'is_pro' => true, 'price_def' => '$35.00', 'url' => '/admin/backups', 'desc' => 'Respaldos privados y actualizaciones ZIP firmadas, con instalación por consola y recuperación de código.'],
            ['id' => 'dating', 'name' => 'Goldensite Love Dating PRO', 'is_pro' => true, 'price_def' => '$24.99/mes', 'url' => '/admin/dating', 'desc' => 'Plataforma profesional de citas, coincidencias y chat VIP.']
        ];
        $manifest=dirname(__DIR__,2).'/modules/GoldensiteForum/module.json';
        if (is_file($manifest)) {
            $forum=json_decode(file_get_contents($manifest),true,8,JSON_THROW_ON_ERROR);
            $modules[]=['id'=>$forum['id'],'name'=>$forum['name'],'is_pro'=>false,'url'=>$forum['admin_url'],'desc'=>'Categorías, preguntas, encuestas y moderación de la comunidad.'];
        }
        $manifest=dirname(__DIR__,2).'/modules/GoldensiteSocial/module.json';
        if (is_file($manifest)) {
            $social=json_decode(file_get_contents($manifest),true,8,JSON_THROW_ON_ERROR);
            $modules[]=['id'=>$social['id'],'name'=>$social['name'],'is_pro'=>false,'url'=>$social['admin_url'],'desc'=>'Ecos con audio, ondas de conversación y presencia voluntaria en el mapa de frecuencias.'];
        }
        $result=array_column($modules,null,'id');
        foreach (ExtensionPackages::installed() as $item) {
            if (($item['module']['native']??null)==='forum') continue;
            $module=$item['module'];
            $id=$module['id'];
            $result[$id]??=['id'=>$id,'name'=>$module['name'],'is_pro'=>$module['pro'],'desc'=>$module['description'],
                'url'=>$module['admin']?'/admin/plugins/'.$id:'/extensions/'.$id,'extension_version'=>$module['version']];
        }
        return $result;
    }
    public static function all(): array
    {
        $modules=self::definitions();
        foreach ($modules as $id=>&$module) {
            if ($id==='deploy-manager' && ProductEdition::current()!=='owner') {
                unset($modules[$id]);
                continue;
            }
            $policy=ModuleCommerce::offer($id);
            if (!in_array($id,['weather-time','forum','social-network','security-audit'],true) && isset($policy['tier'])) $module['is_pro']=$policy['tier']==='pro';
            $module['name']=preg_replace('/\s+PRO$/','',$module['name']).(self::requiresLicenseHere($module)?' PRO':'');
        }
        unset($module); return array_values($modules);
    }
}
