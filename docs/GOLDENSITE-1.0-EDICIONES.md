# GoldenSiteCMS 1.0

La edición **Owner** se instala en el VPS emisor. Ahí se configura la cuenta propietaria, se guarda la clave privada y se firman licencias, catálogos y actualizaciones. La edición **comercial** tiene su propio administrador, creado por el instalador de cada cliente. Verifica las firmas con la clave pública distribuida por Goldensite.

Genera ambos paquetes desde una copia revisada del código en la sede emisora.

```sh
php tools/build-release.php storage/releases/GoldenSiteCMS-1.0-Owner.zip 1.0.0 owner
php tools/build-release.php storage/releases/GoldenSiteCMS-1.0-Comercial.zip 1.0.0 commercial
```

El paquete comercial omite las pantallas, herramientas y clases de emisión Owner. Su instalador omite el registro de emisión. Ambos paquetes se construyen a partir del código y sus dependencias, con la política pública de módulos firmada. Cada instalación genera su propia configuración y clave de aplicación.

Para publicar versiones posteriores, genera el ZIP de actualización a partir del instalador de la edición que vas a actualizar. El manifiesto firmado identifica la edición y la versión de origen. Consulta la [guía de actualizaciones](ACTUALIZACIONES-GOLDENSITE.md).

## Configuración de los módulos

En la edición Comercial, el aviso de cookies se activa por defecto. La política está disponible en `/politica-cookies` y desde el pie de página. En `/admin/gdpr-banner`, completa la identidad, el domicilio, el correo de privacidad y el inventario de proveedores activos de tu instalación. El visitante puede elegir las cookies necesarias o aceptar las opcionales. Puede volver a la política para cambiar la elección.

- **Booking & Appointments.** Activa el módulo, crea un profesional y publica horarios en `/admin/booking`. Comparte `/reservas`. Los horarios usan la zona del sitio y las citas se guardan en el registro existente.
- **Academy.** Crea un borrador, añade lecciones y publica desde `/admin/academy`. En `/academia`, los usuarios se inscriben, completan las lecciones y reciben el enlace verificable al certificado.
- **Events & Tickets.** Publica el evento y su aforo en `/admin/events`. El participante obtiene su QR en `/eventos`. Un administrador con sesión abierta escanea el QR, revisa el código y confirma el acceso. El mismo código se valida una vez.
- **Support Desk.** Activa el módulo y abre una consulta en el buzón existente. Ahí puedes asignarla, elegir estado y prioridad, y guardar notas internas. El historial queda en la conversación administrativa.
- **S3 Connector.** Configura `GOLDENSITE_S3_BUCKET`, `GOLDENSITE_S3_REGION`, `GOLDENSITE_S3_ACCESS_KEY_ID` y `GOLDENSITE_S3_SECRET_ACCESS_KEY` en el entorno privado. Para credenciales temporales, añade `GOLDENSITE_S3_SESSION_TOKEN`. Usa un bucket con bloqueo de acceso público y una identidad limitada a leer y escribir objetos del prefijo autorizado. El conector cifra la copia con una clave derivada de la instalación y verifica su integridad al restaurarla. Conserva la configuración privada y el registro de copias para poder recuperar los archivos. [Configuración de acceso público de S3](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-control-block-public-access.html)

## Proveedor SAML y SCIM

Configura `APP_URL` con el origen HTTPS público de Goldensite y asegúrate de que PHP reconozca HTTPS si usas un proxy. Activa **Goldensite SSO & SCIM Enterprise** con su licencia y abre `/admin/sso`. Introduce el Entity ID del proveedor, su URL de inicio de sesión y el certificado X.509 de firma. El panel muestra el Entity ID de Goldensite y la URL ACS. En el proveedor, configura la firma de la respuesta y de la aserción, y usa el `userName` de SCIM como NameID exacto. [Biblioteca SAML utilizada](https://github.com/SAML-Toolkits/php-saml)

Genera el token SCIM en el mismo panel y cópialo directamente al proveedor. El servidor conserva un hash vinculado al proveedor. La URL base termina en `/api/scim/v2`. El conector cubre el ciclo de vida de usuarios aprovisionados, con filtros de igualdad por `userName`, `externalId` e `id`. Gestiona usuarios mediante GET, POST, PUT, PATCH y DELETE. Las cuentas administrativas, grupos, sincronización masiva y cierre de sesión federado quedan fuera del alcance actual. [Protocolo SCIM 2.0](https://www.rfc-editor.org/rfc/rfc7644)
