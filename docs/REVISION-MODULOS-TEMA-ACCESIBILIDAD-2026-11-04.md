# GoldenSiteCMS: revisión de módulos, tema, búsqueda y accesibilidad

Fecha: 4 de noviembre de 2026. Amplía las revisiones anteriores. No constituye una certificación de ausencia de vulnerabilidades.

## Cambios comprobados

- Consola con paleta derivada del tema activo, paneles heredados adaptados y menús de 16 px con objetivos de al menos 44 px. Se ocultan los índices decorativos; se conservan identificadores y cifras reales.
- Contacto organizado por motivo y módulo, con precios del catálogo compartido y registro de la consulta en el buzón. Un contacto no confirma un pago ni emite una licencia. Formulario comprobado en móvil de 390 px y escritorio de 1366 y 2560 px sin desbordamiento horizontal.
- Navegación compartida en temas: el enlace vinculado sigue el cambio de slug, se oculta al pasar a borrador y se desactiva al eliminar su página. Destinos antiguos inexistentes dejan de mostrarse; permanecen identificados en el editor para su revisión.
- 404 con navegación y tema activos, respuesta HTTP 404 y exclusión de indexación. Mantenimiento valida todo antes de guardar, responde 503 y Retry-After y conserva el acceso administrativo.
- Redirecciones internas 301/302/307/308 con protección de rutas de acceso y detección de ciclos. El monitor agrupa errores reales y permite preparar una regla desde la ruta fallida.

## Búsqueda pública

La cabecera funciona con y sin JavaScript. Busca títulos y cuerpos de páginas y artículos publicados, etiquetas de formularios activos, productos activos cuando su módulo está habilitado y destinos públicos del núcleo. Consulta la base actual: publicar, editar o retirar contenido se refleja en la siguiente consulta. Excluye borradores, formularios inactivos, respuestas privadas y bloques protegidos por paywall. Un borrador canónico prevalece sobre una copia publicada en la tabla histórica.

No busca dentro de archivos adjuntos, panel administrativo, mensajes o datos de clientes. Incluye paginación, consultas preparadas, protección de comodines y límites de consulta. Para volúmenes grandes conviene medir e incorporar un índice específico.

## Accesibilidad

Configuración en /admin/accessibility: habilitar controles, lado del botón y funciones disponibles. Preferencias locales por navegador: tamaño de lectura, espaciado, contraste, subrayado de enlaces, reducción de movimiento y fuente clara; restablecimiento incluido. Diálogo con teclado, Escape y restauración de foco; enlace para saltar al contenido.

La lectura en voz usa únicamente voces locales del navegador y avisa si no están disponibles. No envía el texto seleccionado a servicios externos. Estos controles no sustituyen una auditoría WCAG ni corrigen automáticamente el contenido escrito por terceros.

## Webhooks

Artículos, páginas, consultas, registros de cuentas y bloqueos WAF se guardan en una cola persistente. La entrega comprueba confirmaciones de Discord, Slack y Telegram. JSON usa HMAC SHA-256 sobre timestamp + punto + cuerpo exacto, con identificador de evento. URLs y secretos cifrados en la base, DNS público fijado a la conexión, TLS verificado, sin redirecciones ni proxy y límites de respuesta y tiempos.

HTTP 429/5xx o fallo de conexión permiten hasta cinco intentos con espera. Cambiar endpoint invalida entregas anteriores. El receptor JSON debe verificar firma y antigüedad y deduplicar por identificador: una interrupción entre recepción y confirmación puede ocasionar reenvío.

En el VPS se debe programar el trabajador con el usuario del sitio cada minuto, ajustando las rutas reales:

```cron
* * * * * /usr/bin/php /var/www/goldensite/tools/webhook-worker.php
```

Para actualizar una instalación existente: respaldo previo y ejecutar `php tools/migrate-modules.php` desde la raíz. El instalador nuevo crea el esquema directamente. El trabajador tiene bloqueo de concurrencia. La prueba manual usa la configuración guardada y registra la respuesta real.

Las pruebas automatizadas usan receptores simulados mediante una interfaz disponible exclusivamente en CLI. No se enviaron mensajes a cuentas externas ni se verificaron credenciales reales de proveedores.

## Revisión individual: 18 FREE y 15 PRO

Todas las rutas del catálogo se comprobaron desde una instalación nueva con licencias de prueba aisladas: 32 muestran su pantalla y SSO + SCIM permanece bloqueado explícitamente. Abrir una pantalla no equivale a probar un proveedor externo.

| Módulo | Función revisada y alcance |
|---|---|
| Activity & Audit Log | Historial, filtros, permisos y registro; distingue solicitud de operación exitosa. |
| GDPR & Cookie Banner | Preferencias y bloqueo de scripts opcionales; no certificación legal. |
| Sitemap & Robots | Generación desde contenido publicado y configuración guardada. |
| Webhook Dispatcher | Cola, firmas, confirmaciones de cuatro protocolos, reintentos y pausa. |
| Asset Optimizer | Limpieza HTML conservando scripts, estilos y texto preformateado. |
| Basic QR Generator | Codificación local y salida descargable sin API externa. No se hizo lectura óptica con teléfono. |
| Import / Export | Configuración JSON permitida, transacción y exclusión de secretos y licencias. |
| Blog | Crear, editar, publicar, retirar y buscar; borradores privados frente a copias históricas. |
| Edge WAF & Request Guard | Inspección y límites del núcleo, registro y evento de bloqueo sin datos personales en el webhook. No sustituye endurecer el VPS. |
| Native Metrics | Visitas reales sin IP ni agente del visitante; respeta desactivación. |
| 404 Monitor & Redirects | Estados HTTP, registro, filtros, reglas, rechazo de ciclos y protección de rutas sensibles. |
| Admin Tools | Limpieza mediante el almacén de caché y mantenimiento de sesiones. |
| Maintenance Mode | Guardado atómico, imagen segura, 503, Retry-After y recuperación administrativa. |
| Site Health & Doctor | Comprueba PHP, base, escritura y HTTPS; no es un escáner completo de vulnerabilidades. |
| Live Chat & Engagement | Activación canónica corregida, salas, roles, sanciones con caducidad en la base y revocación. Rechaza campos malformados. |
| Deploy Manager | Versiones de contenido, prueba, publicación y recuperación con respaldo; no despliega servidores ni binarios. |
| Identity & Access | Roles, permisos, sesiones, políticas de red y OIDC; falta validar el proveedor real con sus credenciales. |
| Form Builder | Constructor, publicación, validación, respuestas y exportación privada. |
| Custom Login PRO | Estilos y marca; la seguridad básica no depende de una licencia. |
| Marketplace PRO | Catálogo y licencia; cobros con tarjeta cerrados hasta integrar pasarela real. |
| Server Storage & Downloads PRO | Carga privada, descarga firmada con caducidad y rechazo de firmas alteradas. |
| Ultra Gallery & Motion FX PRO | Guardado y publicación del shortcode con licencia. |
| Universal Importer PRO | Importación transaccional compatible. La migración WordPress/Joomla del sistema no reproduce extensiones, temas ni archivos externos automáticamente. |
| Native Newsletter PRO | Suscriptores y bajas; campañas y entrega masiva requieren integración. |
| Digital Vault & Paywall PRO | Pantalla propia, protección y exclusión del buscador; ya no exige por error licencia de descargas. |
| Commerce & Sales PRO | Productos y catálogo; sin promesa de cobros reales sin proveedor. |
| SEO & Analytics PRO | Metadatos y scripts; modificar scripts requiere contraseña actual y valida identificadores y tamaño. |
| Digital Asset Management PRO | Inventario real y copia WebP comprobable con GD, conservando originales. Sin indicadores ficticios de AVIF o EXIF indexados. |
| Database Cleaner PRO | Operaciones limitadas, permiso y licencia. No se limpió destructivamente la base del dueño durante las pruebas. |
| Centro de Automatizaciones PRO | Reglas y registro de ejecuciones; servicios externos dependen de configuración. |
| SSO + SCIM Enterprise PRO | No disponible ni emitible como producto terminado. Identity & Access ofrece OIDC por separado, sin afirmar SCIM completo. |
| Release Manager & Backup PRO | Respaldo y parches firmados; claves privadas excluidas de ZIP. |
| GoldensiteLove Dating PRO | Licencia y flujos existentes; no se simulan compras VIP ni pasarela. |

La utilidad FTP heredada exige FTP sobre TLS explícito, valida destinos públicos y cifra la contraseña. No se probó un servidor remoto real ni se anuncia despliegue automático mediante esa utilidad.

## Validación y límites

- 541 comprobaciones de instalación, permisos, módulos y regresiones sobre un ZIP extraído con base exclusiva de pruebas.
- Análisis de sintaxis: 357 archivos PHP y 45 tablas declaradas, sin errores de sintaxis, BOM o etiquetas PHP faltantes.
- Navegador: tema conservado, ausencia de “pepe”, menú a 16 px, preferencias persistentes y sin errores JavaScript observados en el recorrido.
- Se conservan datos, archivos originales y credenciales del propietario. La distribución excluye configuración privada, claves Owner, cargas del usuario y binarios locales.

Antes de abrir el VPS al público faltan dominio, HTTPS, correo/proveedores, tareas programadas y una prueba de carga representativa. No se garantiza ausencia absoluta de fallos o vulnerabilidades.
