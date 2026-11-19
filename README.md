# GoldenSiteCMS Comercial

Código de GoldenSiteCMS Comercial 1.0.33, con las mejoras acumuladas de esta edición.

## Requisitos

PHP 8.4.27+ o PHP 8.5.12+, Composer y MariaDB/MySQL compatibles con el esquema incluido. El instalador comprueba las extensiones requeridas.

## Instalación

1. Ejecuta `composer install --no-dev --prefer-dist --optimize-autoloader`.
2. Configura el servidor web con `public/` como raíz del sitio.
3. Crea una base vacía y un usuario dedicado a ella.
4. Ejecuta `php tools/prepare-install.php` y conserva la clave de instalación que devuelve.
5. Abre `/install.php`. Introduce la clave y los datos de conexión, y elige tu cuenta administradora y la URL del sitio.
6. Configura el correo, el segundo factor, los respaldos y los trabajadores de tareas desde el panel.

Para una vista local puedes ejecutar `php -S 127.0.0.1:18098 -t public tools/dev-router.php`. El servidor incluido sirve para desarrollo. Consulta los ejemplos de alojamiento en `deployment/`.

## Edición y actualizaciones

Los archivos `release-edition.txt` y `release-version.txt` identifican la edición Comercial y su versión. Release & Backup verifica los parches firmados antes de instalarlos y conserva un punto de recuperación.

El catálogo incluye módulos gratuitos y módulos sujetos a licencia. Goldensite Security Audit requiere la licencia Comercial correspondiente.

La clave pública incluida verifica paquetes y licencias. Las claves privadas del emisor se mantienen en Owner.

## Publicación del código

La configuración de cada instalación se guarda en `.env` y los datos locales en `storage/`. Las reglas de Git excluyen configuración, cargas, cachés y dependencias instaladas. Composer restaura las dependencias desde `composer.lock`.

## Licencia

Software propietario de GoldenSiteCMS. La disponibilidad del código en un repositorio no concede una licencia de distribución o de uso de módulos de pago.


