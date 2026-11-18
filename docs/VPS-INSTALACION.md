# Publicar GoldenSiteCMS usando el instalador

## Paquete y requisitos

Utiliza el ZIP de distribución generado con `php tools/build-release.php`, no una copia completa de la carpeta de desarrollo. El paquete incluye el código, Composer y sus dependencias instaladas, módulos, temas, pruebas, esquema e instalador. Excluye `.env`, contraseñas, respaldos, bases locales, cachés, archivos de usuarios y los binarios de Windows.

El VPS necesita PHP **8.4.27+** o **8.5.12+**, las extensiones PDO MySQL, mbstring, fileinfo, curl, GD y zip, y MariaDB/MySQL mantenido. La versión probada de la base es MariaDB **11.4.13**. Comprueba los requisitos con `php tools/check-runtime.php` y `composer check-platform-reqs` si utilizas Composer en el servidor.

## 1. Preparar el servidor

1. Extrae el ZIP, por ejemplo en `/var/www/goldensite`.
2. Configura el dominio apuntando a **`/var/www/goldensite/public`** como raíz web. No publiques la raíz completa del proyecto.
3. Usa y adapta `deployment/nginx.conf.example` o `deployment/apache.conf.example`. Apache requiere los módulos rewrite, headers, proxy y proxy_fcgi si utilizas PHP-FPM. Nginx necesita sus reglas explícitas, ya que no utiliza `.htaccess`.
4. Activa HTTPS para tu dominio y comprueba la configuración del servidor antes de reiniciarlo.
5. Crea una base **vacía** y un usuario dedicado con permisos sobre ella: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP y REFERENCES. El instalador funciona con una base ya creada y no necesita permisos globales. No utilices la cuenta root para la aplicación.

El usuario que ejecuta PHP-FPM debe poder escribir en `storage/`, `public/uploads/`, `public/cache/` y `public/downloads/`. Durante la instalación también debe poder crear `.env` en la raíz del proyecto. Concede ese acceso al usuario/grupo del sitio, sin permisos de escritura para todos. Tras instalar, retira la escritura sobre la raíz y el código; conserva la escritura solo en las carpetas de datos necesarias.

No copies `.env.example` a `.env` antes del asistente. El asistente creará la configuración usando los datos que tú introduzcas.

## 2. Habilitar el asistente

Desde la consola del VPS, situado en la carpeta del proyecto, ejecuta la preparación con el mismo usuario que ejecuta PHP. Por ejemplo, si tu servicio usa `www-data`:

```bash
cd /var/www/goldensite
sudo -u www-data php tools/prepare-install.php
```

La consola mostrará una clave privada de instalación. Cópiala; solo autoriza la configuración inicial. Alternativamente puedes configurar `GOLDENSITE_INSTALL_TOKEN`, con al menos 32 caracteres aleatorios, en el entorno de PHP-FPM antes de abrir el asistente.

## 3. Instalar desde el navegador

Abre `https://tudominio.com/install.php` y completa:

- La clave privada de instalación.
- Servidor, puerto, base vacía y credenciales de su usuario dedicado.
- Nombre y URL principal del sitio, sin subcarpetas.
- **El usuario, correo y contraseña administradores que tú quieras utilizar.** La contraseña debe tener entre 12 y 72 bytes.

El asistente valida los requisitos, crea las tablas, guarda los ajustes, registra una cuenta activa con rol administrador y genera `.env` con una clave propia del sitio. La clave de instalación de archivo se retira y el asistente queda cerrado al completar. No existen credenciales administradoras de fábrica.

Si falla antes de finalizar, corrige los datos o permisos y repite con la misma clave, base y credenciales de administrador. El instalador reconoce su propia instalación pendiente. Una base con datos ajenos se rechaza y no se vacía.

## 4. Entrar y comprobar

Abre `https://tudominio.com/login` y utiliza las credenciales que acabas de elegir. Una contraseña errónea muestra un aviso en el formulario. Los intentos repetidos quedan limitados temporalmente.

Comprueba el panel, la creación/publicación de una página y un artículo, el cambio de ajustes y la exportación de un respaldo. Los módulos PRO que requieren licencia mantienen esa condición; el asistente no genera licencias comerciales.

La instalación nueva fue comprobada automáticamente desde el ZIP con una base vacía y un usuario sin permisos globales, incluyendo la creación de la cuenta y el acceso a las pantallas del panel. La configuración del VPS y sus servicios externos necesitan comprobarse también en ese servidor.

## Migrar el contenido local existente

Una instalación nueva no incluye los datos de tu equipo. Para trasladarlos, respalda la base actual y copia los archivos de `public/uploads/` y otros archivos privados necesarios a través de un canal seguro. El respaldo SQL contiene cuentas, hashes de contraseñas y datos personales; no lo subas dentro de `public/` ni lo compartas como un paquete de código. Restaurarlo puede sustituir las credenciales elegidas en una instalación nueva: usa entonces las credenciales de la base restaurada.

No copies la carpeta de datos binarios de XAMPP al VPS. Usa un respaldo lógico probado, configura un usuario dedicado en el VPS y conserva una copia previa antes de restaurar.

## Integraciones y alcance

La instalación de un cliente no habilita el panel de emisión Owner. Para trasladar tu sede emisora, sus claves e historial, sigue `OWNER-Y-LICENCIAS.md`. El ZIP solo contiene la identidad pública de verificación y una copia pública de los precios vigentes; no contiene tus credenciales Owner ni su clave privada. La disponibilidad funcional por módulo está detallada en `REVISION-MODULOS.md`.

El acceso administrador y el núcleo del CMS se prueban sin servicios externos. Las campañas de correo, SMS, SSO y otros proveedores requieren configuración y pruebas con sus credenciales. Los pagos con tarjeta necesitan una pasarela real y confirmaciones autenticadas; permanecen cerrados mientras no exista. El constructor Android produce un manifiesto, y los APK/AAB requieren SDK y firma.

El servidor `php -S` es para desarrollo local. Para publicar el sitio utiliza Apache/Nginx con PHP-FPM, HTTPS y los permisos indicados.

Referencias: [PHP en Unix](https://www.php.net/manual/en/install.unix.php), [raíz y mapeo de URL en Apache](https://httpd.apache.org/docs/2.4/urlmapping.html), [configuración de Nginx](https://docs.nginx.com/nginx/admin-guide/basic-functionality/managing-configuration-files/).
