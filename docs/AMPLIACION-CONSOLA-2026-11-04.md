# Goldensite: edición, identidad, formularios y migración

## Acceso local y requisitos

La instalación de desarrollo continúa en `http://localhost:8000`. Para iniciarla desde PowerShell en VS Code:

```powershell
Set-Location 'D:\GOLDENSITECMS'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\tools\start-server.ps1'
```

El script selecciona el PHP mantenido del proyecto y prepara la base local. No abras un segundo servidor en el mismo puerto. PHP `-S` es para desarrollo; usa el instalador con Apache/Nginx y HTTPS en el VPS.

Se añadió GD a los requisitos para generar iconos PNG a partir de imágenes verificadas. El PHP local ya tiene GD habilitado. En el VPS instala/habilita GD y configura `upload_max_filesize=24M`, `post_max_size=26M`, `memory_limit=256M` o valores compatibles. La importación externa admite hasta 20 MB; el WAF mantiene su límite general de solicitudes.

## Tipografía e idiomas

`/admin/typography` configura fuentes y tamaños de la web pública y de la consola de forma independiente. Ofrece seis familias del sistema con alternativas; no requiere servicios externos de fuentes. Los tamaños de texto admiten 12–28 píxeles y los títulos 24–80. Las páginas creadas pueden heredar la configuración global o tener su propio perfil. Las jerarquías y textos auxiliares conservan escalas relativas o límites de su diseño.

El núcleo dispone de catálogos de interfaz en español, inglés y portugués, con idioma administrativo y público separados. El selector público conserva el idioma en una cookie y vuelve a la página actual. El idioma no traduce automáticamente contenido editorial, marcas, nombres, código o mensajes de clientes. Las traducciones de páginas se introducen expresamente en la sección de traducciones; si faltan, se utiliza el contenido original. Los textos de extensiones anteriores sin entrada en el catálogo conservan su idioma original, en lugar de inventar una traducción.

## Páginas, artículos y contacto

Páginas y Artículos comparten un editor con listado y búsqueda, edición por identificador, borradores, publicación, SEO y modos visual/HTML. El editor y su vista previa utilizan la paleta activa del sitio. La vista previa está dentro de un iframe aislado sin ejecución de scripts ni acceso a la sesión administrativa. Crear una dirección que ya existe no sobrescribe una página; debe abrirse para editarla. Se detectan revisiones antiguas al guardar una edición. Los roles editoriales granulares reciben HTML saneado; el administrador completo conserva las capacidades de código explícitamente autorizadas.

Contacto tiene una composición empresarial propia de Goldensite, formulario adaptable, campos etiquetados, validación del servidor, consentimiento de uso de datos, límite de envíos, campo contra automatismos y comprobantes con referencia. El envío redirige a la confirmación para evitar duplicados al actualizar. El mensaje queda en el buzón de la administración.

El buzón ofrece búsqueda, estados, conversación y respuestas por SMTP. El destinatario se toma del registro guardado. Las respuestas pueden quedar como borradores, aceptadas por el servidor SMTP o fallidas; un fallo nunca se marca como respuesta enviada. La aceptación SMTP no confirma que el proveedor entregue el correo en la bandeja final del destinatario. El envío real necesita la configuración y credenciales del proveedor.

El registro de actividad tiene filtros por evento, texto y fechas, paginación y CSV protegido frente a fórmulas. La pantalla no permite borrar silenciosamente el historial.

## Iconos, módulos y precios

Los iconos administrativos se dibujaron como SVG de Goldensite sobre una retícula común, con trazos, esquinas y proporciones propias. No se añadió una biblioteca de iconos de terceros. La navegación, el catálogo y los nuevos editores los utilizan; la estructura común sustituye los pictogramas heredados en etiquetas administrativas.

El catálogo tiene tarjetas diferenciadas FREE/PRO, búsqueda y filtros por tipo y estado, icono por módulo, precio y controles de activación. Los precios PRO continúan teniendo una fuente común: el precio guardado en Owner se consulta en la tarjeta del mismo módulo y se exporta al construir el paquete público. Owner conserva su autorización y contraseña independientes. Los precios locales de instalaciones de clientes no se sincronizan por internet sin un servicio de catálogo; el ZIP incorpora los precios públicos del momento de su construcción.

Los tres nuevos módulos se incorporaron como FREE, sin inventar tarifas ni exigir licencias nuevas: el catálogo pasa a 33 módulos, 18 FREE y 15 PRO. Las restricciones comerciales y funciones pendientes de otros módulos de la revisión anterior siguen vigentes.

El icono global se carga desde Configuración sin reorganizar sus secciones anteriores. Admite PNG/JPEG/WebP verificados, de hasta 2 MB y entre 32 y 2048 píxeles por lado; se recorta y genera un PNG de 256×256. No se publican SVG subidos ni archivos originales ejecutables. El icono se aplica a páginas públicas, acceso y consola; los navegadores pueden necesitar una recarga completa.

## Goldensite Identity & Access

El directorio del dashboard permite crear usuarios, bloquear/desbloquear cuentas y cambiar contraseñas con confirmación de la contraseña actual. Una cuenta bloqueada pierde acceso en su siguiente solicitud. La cuenta Owner tiene protecciones adicionales y no puede bloquearse desde el directorio. Un gestor granular no puede crear administradores completos ni cambiar sus credenciales.

Los roles tienen permisos por pantalla y operación, redes IPv4/IPv6/CIDR opcionales y exigencia de segundo factor. El control se aplica en el servidor en cada solicitud, también usando la ruta alternativa del panel. Los administradores completos mantienen acceso de recuperación; Owner permanece separado. Las rutas de módulos heredados que no tienen permisos granulares declarados quedan reservadas al administrador completo. Exportar/restaurar toda la base, publicar scripts de integración y publicar documentos HTML completos son operaciones de administrador completo.

En `/account/security` cada usuario autenticado puede configurar su autenticador TOTP. El secreto se cifra; la activación exige contraseña y código válido. Se muestran ocho códigos de recuperación una sola vez, almacenados como hashes y consumidos individualmente al usarlos. La desactivación exige contraseña y un código actual. Las cuentas no deben perder estos códigos. Los secretos de autenticadores anteriores siguen siendo compatibles durante la transición.

El acceso empresarial implementa OpenID Connect con autorización por código, PKCE S256, estado, nonce, vencimiento y verificación de firma mediante la dependencia mantenida de JWT. Restringe conexiones a HTTPS público con verificación TLS, direcciones fijadas tras resolver DNS, límites de respuesta y sin seguir redirecciones del servidor. Verifica emisor y audiencia exactos; no vincula ni eleva privilegios por correo electrónico. El administrador vincula explícitamente el `subject` del proveedor con una cuenta local no administrativa. Se puede exigir MFA mediante un autenticador local o la declaración MFA firmada del proveedor. SAML y SCIM no se implementaron en este módulo.

Para utilizarlo, registra una aplicación en el proveedor y configura Issuer, Client ID, secreto y callback `https://tu-dominio/login/enterprise`. El secreto se cifra. Las pruebas locales verifican controles y rechazo de datos inválidos; falta una prueba integral con el proveedor real del usuario. La función permanece desactivada hasta configurarla. No desactives el acceso local de recuperación.

Referencia de autorización: [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

## Goldensite Form Builder

El constructor permite añadir, ordenar y eliminar campos de texto, correo, texto largo, número, fecha, selección y consentimiento. Los identificadores son únicos, las etiquetas están limitadas y el servidor valida tipos, obligatoriedad y opciones. Se admiten hasta 30 campos. Cada formulario tiene borrador/publicación, dirección pública `/forms/direccion` y shortcode `[goldensite_form id="ID"]` para páginas o artículos.

El módulo debe estar activo para servir formularios públicos. Las respuestas se guardan en tablas privadas, con paginación y exportación administrativa. Hay CSRF, límites de envío y comprobantes de un solo uso que evitan duplicar respuestas al reenviar. Los registros personalizados recopilan datos; no crean cuentas ni conceden roles automáticamente. No admite cargas de archivos ni procesa pagos.

## Goldensite Deploy Manager

Versiona contenido y ajustes públicos de esta instalación: páginas, artículos, navegación, tema, tipografía, idioma y portada compuesta. Crear una versión produce un entorno de contenido en prueba. Las ediciones de las páginas dentro de ese entorno no afectan a producción. La vista previa es aislada.

Publicar exige confirmación y contraseña. Se comprueba integridad con HMAC, se conserva una instantánea anterior y se aplica el cambio en una transacción; las publicaciones simultáneas se serializan. Las versiones anteriores y respaldos se pueden recuperar como producción. No sustituye usuarios, pedidos, formularios recibidos, contraseñas o claves Owner. El contenido nuevo creado después de preparar una versión se sustituirá al publicar esa versión; queda en el respaldo previo.

Este módulo no aprovisiona VPS ni cambia binarios, archivos multimedia o servicios del sistema. Las actualizaciones de código firmado siguen gestionándose en Release Manager & Backup. Conserva `.env` y el almacén de claves junto a los respaldos privados para poder verificar versiones después de trasladar la instalación.

## Estudio de composición

La pantalla de `ai-builder` ahora separa dirección creativa, generación de borrador, código HTML, vista previa de escritorio/móvil, publicación e historial. El generador local crea composiciones Studio, Editorial o Product a partir de los datos introducidos. No finge utilizar un modelo de inteligencia artificial ni envía datos a un proveedor inexistente. Es posible editar el HTML, guardar un borrador y recuperar una versión anterior como borrador antes de publicarla.

La publicación de HTML completo requiere un administrador completo y su contraseña. La vista previa no ejecuta scripts. Al desactivar la portada compuesta vuelve el tema existente y se conserva el borrador. Los antiguos endpoints del manifiesto siguen siendo compatibles; el estudio no construye aplicaciones Android.

## Importación de WordPress y Joomla

Configuración conserva la restauración SQL completa de Goldensite y añade un asistente independiente para WordPress/Joomla. No deben confundirse: restaurar un volcado externo directamente no convierte su esquema ni hace compatibles sus plugins.

El asistente lee volcados MySQL/MariaDB de valores literales, sin ejecutar sus sentencias. Reconoce columnas desde `CREATE TABLE` o desde los `INSERT`, respeta comillas, escapes y punto y coma dentro de textos. Convierte páginas y entradas nativas de WordPress y artículos de Joomla (`introtext` y `fulltext`). Conserva estados compatibles, adapta enlaces internos por ID que reconoce y sanea HTML externo mediante HTML Purifier. El contenido protegido o con dependencias de shortcodes/bloques dinámicos queda en borrador.

Primero se presenta un análisis revisable con cantidades, muestras y limitaciones. El plan es privado, caduca en una hora y está ligado a la cuenta que lo creó. Confirmar requiere contraseña y la palabra IMPORTAR. Se crea una copia SQL privada de Goldensite antes de aplicar. Las inserciones son transaccionales: si hay una colisión de dirección, usuario o correo se revierte el lote. Confirmar otra vez el mismo análisis no duplica registros.

Los usuarios se convierten opcionalmente como cuentas bloqueadas, sin privilegios administrativos y con contraseña nueva desconocida. Asigna una contraseña desde el directorio y habilita cada cuenta expresamente. No se reutilizan hashes incompatibles o contraseñas inseguras de otros CMS.

Límites: SQL UTF-8 sin compresión de hasta 20 MB, 2.000 contenidos, 20.000 registros fuente y 200 usuarios por lote. No importa automáticamente archivos multimedia, temas, tiendas, permisos originales, formularios ni extensiones específicas. Los menús y enlaces no reconocidos requieren revisión. Otros CMS necesitan adaptadores propios. Una base de datos no contiene los archivos del sitio: para una migración completa es necesario revisar el volcado real, su versión, extensiones y archivos asociados. Hasta recibir ese material no se puede certificar equivalencia funcional de un sitio WordPress/Joomla concreto.

Referencias de estructuras nativas: [WordPress WP_Post](https://developer.wordpress.org/reference/classes/wp_post/), [tabla de contenido de Joomla](https://docs.joomla.org/Tables/content).

## Verificación

`tests/run.php` comprueba el núcleo con HTTP y MariaDB. El instalador ejecuta las pruebas Owner, módulos, consola y `tests/expansion.php` sobre un ZIP extraído y una base vacía separada. Las pruebas nuevas ejercitan tipografía, idiomas, edición, roles, cuentas bloqueadas, MFA y recuperación, formularios y respuestas, prueba/publicación/rollback, iconos e importación WordPress/Joomla con SQL que incluye comandos que deben ignorarse. Los proveedores SMTP y OIDC reales requieren validación con las credenciales del usuario.

Las comprobaciones visuales locales se realizaron en escritorio de 1440 píxeles y móvil de 420 píxeles, incluyendo interacción de editores, filtros, campos de formularios y aislamiento de previsualizaciones. Los resultados de pruebas y sus límites no constituyen una garantía de ausencia de vulnerabilidades futuras.
