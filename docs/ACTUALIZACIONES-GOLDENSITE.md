# Actualizaciones de GoldenSiteCMS

## Consolidación 1.0.37

Esta entrega conserva los motores nativos de chat, boletín y consentimiento. Las rutas antiguas de plugins y del banner cargan la misma implementación mediante entradas de compatibilidad; no se eliminan sus datos ni se mantienen clases alternativas. Los temas Dealer y Library conservan su identidad y comparten la estructura de presentación.

El chat y su moderación utilizan el esquema canónico del migrador. Se conservan salas, mensajes y permisos existentes; un fallo de almacenamiento se presenta como indisponibilidad, no como una conversación vacía ni como autorización para ignorar una sanción. El inventariador `php tools/audit.php` identifica clases duplicadas y devuelve error cuando existen. El generador de instaladores también rechaza esas colisiones.

Los instaladores de ambas ediciones se construyen desde la fuente consolidada, y el parche firmado 1.0.36 → 1.0.37 incorpora todos sus archivos actualizables. Esto repone, entre otros, la vista pública y la administración de la política de cookies cuando faltan en una instalación que declara 1.0.36. No reemplaza usuarios, contenido, claves privadas ni configuración local. Los archivos de recuperación se conservan.

El generador acepta como cuarto argumento un instalador base de la misma edición para conservar su catálogo público firmado y sus precios. El generador de parches admite como quinto argumento ese instalador de origen: omite el router de desarrollo si sus bytes no cambiaron, para permitir las pruebas desde Windows sin reemplazar un archivo que el servidor mantiene abierto. Las nuevas instalaciones del actualizador también evitan reescribir contenidos idénticos.

Si MariaDB/MySQL invalida la lectura consistente de un respaldo por un cambio concurrente de estructura (error 1412), se descarta la copia parcial y se reintenta una sola vez desde una transacción nueva. Si vuelve a fallar no se anuncia un respaldo válido. El generador rechaza conexiones que ya tengan una transacción pendiente para no intervenir trabajo ajeno. Mantén detenidas las escrituras y los cambios de esquema durante las actualizaciones y pruebas de restauración.

Antes de instalar, verifica la edición y versión de origen; utiliza la firma correspondiente al ZIP. Los cambios de código que el administrador haya realizado directamente pueden ser reemplazados por esta actualización completa; el actualizador guarda el respaldo previo. Guarda tus personalizaciones y revisa el manifiesto antes de instalar.

## Consultar el canal comercial

La edición Comercial consulta `https://goldensitecms.com/updates/commercial/<version-instalada>.nfx`. En **Release Manager & Advanced Backup**, abre **Buscar actualizaciones oficiales**. También puedes ejecutar `php tools/release-update.php check` desde la raíz del CMS. La consulta comparte la versión instalada en la ruta solicitada y verifica el aviso con la clave pública de GoldenSiteCMS. Si encuentra un paquete, muestra el ZIP y su firma. La instalación se realiza con el procedimiento de preparación y aplicación descrito abajo.

El ZIP Comercial r2 original no incluye esta consulta. Para instalaciones nuevas usa la revisión que incluye el canal. Una instalación anterior debe incorporar el actualizador mediante un paquete firmado con una versión de destino superior.

## Aplicar una actualización

En **Release Manager & Advanced Backup** (`/admin/backups`), selecciona el ZIP, pega la firma del archivo `.signature.txt` y pulsa **Verificar y preparar ZIP**. Cuando aparezca la versión de destino, confirma tu contraseña y pulsa **Instalar actualización**. El panel vuelve a verificar el paquete, respalda la base de datos y los archivos que cambiarán y muestra la versión instalada. Puedes descargar el respaldo SQL desde el historial del módulo.

Si tu instalación separa la cuenta de PHP de la cuenta de despliegue, usa la opción **Instalación por consola para administradores del servidor**. Ejecuta el comando desde la raíz del CMS con la cuenta de despliegue. El servidor web necesita escribir en el buzón privado `storage/update-inbox`. Mantén `.goldensite-release` fuera de la raíz web y configura `public` como raíz del sitio.

```sh
php tools/release-update.php apply ID_QUE_MUESTRA_EL_PANEL
```

En producción, detén las colas y las escrituras externas durante la actualización y deja terminar las solicitudes en curso. Si tu PHP mantiene código en caché sin comprobar cambios, reinicia los procesos PHP antes de reabrir el tráfico. El CMS devuelve HTTP 503 a las solicitudes nuevas de la aplicación mientras instala.

La consola muestra la versión instalada, la ruta del respaldo SQL y un comando de recuperación autónomo. Guarda ese comando con el registro del despliegue. El respaldo contiene datos privados y debe protegerse con los mismos permisos que la base de datos. La recuperación revierte el código y la versión. Las migraciones admitidas son aditivas. Si hace falta recuperar la base de datos, usa el respaldo SQL con el procedimiento de restauración de tu servidor, considerando las escrituras posteriores al respaldo.

## Preparar una versión en la sede emisora

```sh
php tools/build-release.php storage/releases/Goldensite-1.0.1-commercial.zip 1.0.1 commercial
php tools/build-update.php storage/releases/Goldensite-1.0.1-commercial.zip 1.0.0 1.0.1 storage/releases/Goldensite-1.0.1-update.zip
php tools/publish-update.php storage/releases/Goldensite-1.0.1-update.zip storage/releases/publicar-1.0.1
```

En el servidor de distribución, crea `public/updates/commercial/` como directorio escribible por el usuario de PHP. Desbloquea Owner y entra en **Deploy Manager → Publicar una actualización comercial**. Adjunta el ZIP y el aviso `1.0.0.nfx` que generó el último comando y confirma tu contraseña administrativa. El panel verifica las dos firmas, el hash y la edición. Guarda primero el ZIP y sustituye el aviso en una sola operación. El tamaño máximo del ZIP es 20 MB y el límite de la solicitud debe dejar espacio para ambos archivos y los campos del formulario.

En el servidor Apache del canal, la portada puede listar los avisos firmados. Copia `deployment/commercial-channel-index.php` a `public/updates/commercial/index.php`, conserva el diseño estático en `index.html` y configura `DirectoryIndex index.php index.html` en el `.htaccess` del canal. El índice muestra solo avisos válidos con un ZIP disponible y se actualiza conforme se publiquen nuevas versiones.

Sirve ambos archivos por HTTPS en `goldensitecms.com`, con HTTP 200 directo. Mantén también `www.goldensitecms.com` como alias si tienes clientes que consultan esa dirección. Para el aviso, usa revalidación o una caché corta. Conserva los ZIP que ya se ofrecieron a los clientes. Devuelve HTTP 404 si no hay aviso para la versión solicitada. Los números del ejemplo son ilustrativos.

Distribuye el ZIP de actualización y su firma. La clave privada permanece en la sede emisora. Cada paquete parte de una versión exacta y debe avanzar a una posterior. El paquete actualiza archivos de código y admite `database/schema.sql` para tablas y columnas nuevas. Las retiradas de archivos y los cambios destructivos de esquema requieren un procedimiento de despliegue aparte. Configuración local, licencias, almacenamiento privado, cargas y cachés se conservan.

Las instalaciones antiguas que todavía muestran «Aplicar Parche / Actualización» necesitan incorporar primero este actualizador mediante un despliegue de confianza. El ZIP de instalación nueva se reserva para instalaciones nuevas.

Los webhooks JSON envían `X-Goldensite-Event-ID`, `X-Goldensite-Timestamp` y, cuando configuras un secreto, `X-Goldensite-Signature`. Si el receptor utiliza encabezados de una instalación anterior, actualiza sus nombres antes del cambio. La firma sigue siendo HMAC SHA-256 del timestamp, un punto y el cuerpo JSON recibido.
