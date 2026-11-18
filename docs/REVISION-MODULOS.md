# Revisión de módulos y plugins — 3 de noviembre de 2026

Se revisaron el catálogo de 30 módulos, sus controladores y rutas, las implementaciones físicas en `modules/` y `plugins/`, el esquema, los consumidores públicos y los mecanismos alternativos de activación. Se añadieron pruebas HTTP con escrituras reales sobre una instalación extraída del ZIP y una base dedicada. Las pruebas no modifican ventas, licencias o contenidos de la instalación del dueño.

## Correcciones transversales

- Owner restringido a una cuenta configurada por consola, contraseña adicional con hash, límite de intentos, caducidad de desbloqueo y comprobación del permiso en las operaciones.
- Catálogo y precios compartidos; aliases normalizados. Desactivar el identificador canónico prevalece sobre un alias antiguo que siga marcado activo.
- Licencias firmadas con clave privada excluida de la distribución; verificación pública independiente de la base emisora. Comparación exacta del módulo, dominio desde `APP_URL`, vencimiento y revocación. Se retiraron activaciones por prefijo, coincidencias SQL parciales y tokens públicos de verificación.
- Pagos declarados separados de pagos confirmados; referencia única, confirmación idempotente y trazabilidad. No se simulan envíos de claves.
- Control de licencia también en rutas directas de guardado, importación, limpieza, SSO, descargas y respaldos.
- Se unificaron lectores y puntos de entrada alternativos de plugins para evitar que una tabla o controlador antiguo eluda estas comprobaciones.
- Caché de estados durante cada solicitud e índices para consultas de chat, métricas y licencias. No se realizó una prueba de carga de producción.
- Los parches del núcleo requieren una firma del archivo exacto. Los respaldos pueden descargarse desde una ruta administrativa protegida y no incluyen la clave privada Owner.

## Evaluación de los 15 módulos gratuitos

| Módulo | Revisión y alcance actual |
|---|---|
| Activity & Audit Log | Registro conectado a autenticaciones y solicitudes administrativas. La etiqueta de solicitud no afirma que la operación haya terminado con éxito. El historial de licencias se conserva en su auditoría específica. |
| GDPR & Cookie Banner | Banner integrado en frontend y portada personalizada, preferencias esenciales/todas, validación de color y posición. Los scripts opcionales SEO esperan consentimiento. Es una herramienta de preferencias, no una certificación legal. |
| Sitemap & Robots | Implementación canónica compartida, contenido publicado, URL configurada y robots guardado. Sin archivos estáticos antiguos que oculten ajustes nuevos. |
| Webhook Dispatcher | Salida HTTPS mediante cliente que bloquea destinos internos. Publicaciones conectadas al despachador Discord compatible. No se probaron envíos a proveedores reales. |
| Asset Optimizer | Eliminación de comentarios HTML no esenciales conservando scripts, estilos y texto preformateado. Se retiró la promesa de conversión WebP automática y de caché dinámica que no se aplicaban. |
| Basic QR Generator | Generador local del panel y codificador PHP instalado mediante Composer. La batería base comprueba SVG válido; no se realizó lectura óptica desde un teléfono. |
| Import / Export | Pantalla propia para transferir configuración básica JSON con lista de campos permitidos y transacción. No importa usuarios, contraseñas, licencias ni código. |
| Blog | Publicación desde las tablas existentes, búsqueda, detalle y paginación comprobados; los borradores importados permanecen privados. |
| Edge WAF | Inspección del núcleo conservada. No se presenta como sustituto de consultas preparadas, permisos o actualización del servidor. |
| Native Metrics | Conectado a visitas HTML reales. Guarda rutas sin consultas, sin IP ni agente del visitante; desactivación comprobada incluso con alias antiguo activo. |
| 404 Monitor & Redirects | Redirecciones internas 301/302, contador de usos, registro real de 404, protección de rutas de acceso y detección de ciclos. |
| Admin Tools | Limpieza de caché y sesiones. Se corrigió el aviso que decía reconstruir rutas cuando solo borraba caché HTML. |
| Maintenance Mode | Ajustes unificados, respuesta pública 503 y acceso de administrador/login conservado. |
| Site Health & Doctor | Comprueba PHP requerido, base, escritura y HTTPS. La puntuación se limita a esas comprobaciones básicas. |
| Live Chat | Página y API conectadas, autor tomado de la sesión, mensajes escapados, últimos 100 mensajes, límites y salas públicas/privadas/VIP comprobadas. Un nombre de usuario reservado por defecto no concede moderación. |

## Evaluación de los 15 módulos PRO

| Módulo | Revisión y alcance actual |
|---|---|
| Custom Login | Ocho estilos y formularios comprobados por HTTP; marca e imágenes configurables con validación contra inyección en CSS. Los controles básicos de acceso del núcleo funcionan sin comprar este módulo. CAPTCHA no se presenta como implementado. |
| Marketplace | Licencia y activación en acceso público, catálogo y administración conservados. No se habilitan tarjetas ni se anuncia una pasarela configurada guardando solo un fragmento de clave. Pagos reales pendientes de integración. |
| Storage & Downloads | Carga HTTP real a almacenamiento privado, enlace temporal firmado, descarga y rechazo de firmas alteradas comprobados. Se corrigió el enlace público sin firma. |
| Gallery | Imágenes guardadas desde líneas separadas, activación firmada y publicación de shortcode comprobadas. |
| Universal Importer | Se sustituyó el contador ficticio de sentencias SQL por importación transaccional real de artículos JSON como borradores de texto. Rechaza duplicados; no importa SQL/XML, medios ni garantiza equivalencia de un sitio WordPress/Joomla entero. |
| Newsletter | Gestión y baja de suscriptores. Campañas y entrega real de correo requieren integración adicional; no se declaran boletines automáticos operativos. |
| Digital Vault & Paywall | Acceso por sesión y derecho vigente; las funciones antiguas delegan en el mismo motor. Un booleano pasado al helper antiguo no concede suscripción. Descargas requieren firma y módulo habilitado. |
| Commerce & Sales | Catálogo, precios del servidor y pedidos pendientes conservados. Marcar un pedido desde administración no equivale a una confirmación bancaria. Cobro por tarjeta pendiente. |
| SEO & Analytics | Metadatos conectados al HTML público con URL canónica configurada. IDs de rastreo validados y scripts opcionales sujetos al consentimiento; no se enviaron eventos externos de prueba. |
| DAM | Inspección de activos existentes; control de licencia. No se ha implementado un procesador multimedia profesional ni una biblioteca de transformaciones. |
| Database Cleaner | Acciones protegidas además de la pantalla; respaldo SQL y limpieza de sesiones/optimización existentes. No elimina contenido comercial como parte de una limpieza rutinaria. |
| Automation Hub | Formulario conectado, validación de eventos/condiciones/acciones, eventos reales de publicación/registro/contacto y registro local. Prueba manual rotulada `VALIDATED`, no ejecución ficticia. Correo/webhooks requieren proveedor; demoras no habilitadas sin trabajador de cola. |
| SSO + SCIM | **Pendiente y bloqueado para emisión/activación comercial.** Solo existía una pantalla de ajustes; no había autenticación SAML/OIDC ni endpoints SCIM funcionales. Necesita una implementación y pruebas específicas con un IdP. |
| Release & Backup | Respaldo de código, base y archivos públicos; descarga administrativa, nombres sin sobreescritura y rechazo de respaldo SQL parcial. Parches requieren firma y validación de rutas/sintaxis. `.env`, claves privadas y archivos privados de `storage/` se respaldan por separado. |
| Dating | Control de activación, moderación, perfiles y transferencias de regalos existentes conservados; pruebas base de precio y doble gasto. No hay cobros con tarjeta, recarga automática ni compra VIP simulada. La evaluación no equivale a probar cada interacción social en producción. |

## Evidencia y límites

`tests/run.php` cubre sesiones, CSRF, TOTP, cargas, rutas, SQL, firmas de descarga, precios, doble gasto y acceso HTTP. `tests/install.php`, junto con `tests/owner.php` y `tests/modules.php`, instala un ZIP nuevo con un usuario SQL sin permisos globales y comprueba el circuito de Owner/licencias y acciones funcionales de módulos. También activa las pantallas PRO disponibles con códigos de prueba; SSO se verifica como bloqueado.

La prueba de consumidor retira temporalmente, dentro de su entorno aislado, la configuración Owner, la clave privada y la tabla de emisión: la activación sigue funcionando mediante la clave pública. Se verifican rechazo de otro administrador, código alterado, módulo/dominio incorrecto, caducidad, revocación, confirmación duplicada, fuerza bruta y cambio de contraseña Owner.

Abrir una pantalla no verifica todos sus procesos internos. Las comprobaciones anteriores describen lo que se ejercitó; servicios externos, navegadores/dispositivos, carga y configuración del VPS deben verificarse en el entorno definitivo. La revisión no certifica ausencia absoluta de vulnerabilidades. No se añadieron módulos comerciales nuevos mientras existen integraciones pendientes.

## Ampliación del 4 de noviembre

El catálogo tiene ahora 33 módulos, incluyendo Deploy Manager, Identity & Access y Form Builder como FREE. Consulta `AMPLIACION-CONSOLA-2026-11-04.md` para el alcance y las pruebas de estas funciones.
