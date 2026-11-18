# Goldensite Storage & Downloads

## Para qué sirve

Es una biblioteca privada para distribuir manuales, documentos, recursos y paquetes de versiones. Al activarla no aparecen archivos de ejemplo: la biblioteca comienza vacía. Un caso práctico es entregar el ZIP de un plugin a un cliente concreto, durante 48 horas y con dos accesos permitidos.

El archivo permanece en `storage/downloads`, fuera de `public`, con nombre aleatorio y extensión interna `.bin`. La entrega usa un controlador que comprueba el módulo, la firma, los permisos y la integridad. Guardar un ZIP no instala ni ejecuta su código. Este módulo tampoco procesa pagos ni analiza archivos con un antivirus.

## Flujo de uso

1. En `/admin/downloads`, completa título, descripción, colección y versión; selecciona un archivo de hasta 20 MB.
2. Elige acceso mediante enlace válido, con sesión iniciada o exclusivamente para una cuenta asignada.
3. Abre la ficha del recurso y genera un enlace revocable: vigencia de 1 a 720 horas, límite de accesos y destinatario opcional.
4. Comparte el enlace con el destinatario. Para un recurso restringido, esa persona debe iniciar sesión con la cuenta autorizada.
5. Revisa las autorizaciones de entrega, revoca enlaces o archiva el recurso cuando corresponda.

Para mostrar una descarga en una página, copia «Insertar en una página» desde la ficha del enlace y pega ese código en el contenido. La tarjeta utiliza el tema vigente, muestra el recurso real y conserva los permisos del archivo y del enlace. Ver la tarjeta no consume una descarga. Si revocas el enlace, la tarjeta deja de ofrecerlo; solicita otro enlace al administrador.

Los enlaces son credenciales de acceso: compártelos solo con sus destinatarios. Un límite de accesos cuenta solicitudes autorizadas, no personas distintas ni descargas completas. Volver a abrir un enlace consume otro acceso; no hay soporte de reanudación por rangos HTTP.

## Versiones y conservación

Para añadir una versión, selecciona un recurso existente e introduce una etiqueta nueva. Conserva los bytes de versiones anteriores. Hereda sus permisos y colección salvo que marques expresamente la opción de sustituirlos. Las etiquetas no se repiten dentro del mismo recurso.

Editar permite cambiar título, descripción, colección y permisos; los bytes y la etiqueta de versión permanecen inmutables. Archivar bloquea nuevas entregas y permite reactivar después. La eliminación definitiva exige contraseña actual y confirmación; revoca los enlaces, retira el archivo físico y conserva la actividad. No cancela una transmisión que ya haya comenzado.

Los enlaces temporales antiguos se mantienen por compatibilidad con los recursos existentes. Se pueden retirar por archivo; para nuevas entregas, utiliza los enlaces revocables.

## Seguridad y operación

- Administración reservada a la cuenta con privilegios máximos; cambios protegidos por sesión y CSRF.
- Comprobación de permisos vigentes en cada entrega, incluso cuando alguien copia una URL.
- Firma HMAC ligada al archivo y al identificador del enlace; caducidad, destinatario y revocación comprobados en servidor.
- Contador de accesos actualizado con una condición atómica para evitar exceder su límite.
- SHA-256 antes de entregar: un archivo modificado se rechaza. Esto comprueba integridad, no que su contenido sea seguro.
- Respuesta como adjunto binario, sin caché y con protección contra interpretación como contenido del sitio.
- Registro de autorizaciones, sin guardar el token del enlace ni afirmar que el navegador recibió todos los bytes.

En el VPS, el directorio raíz del servidor web debe ser `public`, y el usuario PHP debe poder leer/escribir en el almacenamiento privado. Ajusta `upload_max_filesize` y `post_max_size` para permitir el tamaño elegido; el límite del módulo sigue siendo 20 MB. Vigila espacio y copias de seguridad. Los archivos subidos requieren una política propia de revisión de contenido antes de compartirlos.

## Comprobaciones automatizadas

`tests/dating-downloads.php` comprueba, dentro de una instalación y base de pruebas independientes: carga HTTP real de un ZIP con HTML, igualdad SHA-256 de los bytes descargados, restricciones por usuario, límite de un acceso, revocación, vencimiento, destinatario, archivo archivado, integridad alterada, herencia y duplicados de versiones, desactivación del módulo aunque Vault siga activo y eliminación con contraseña. Los datos de prueba no se crean en la biblioteca del propietario.

## Propuestas de plugins complementarios

Estas son propuestas, no módulos ya instalados:

| Plugin | Función concreta | Integración prevista |
|---|---|---|
| Goldensite Booking & Appointments | Reservas por servicio y profesional, disponibilidad, zonas horarias, cancelaciones y prevención de solapamientos. | Cuentas, formularios y cola de recordatorios existentes. |
| Goldensite Academy | Cursos, lecciones, cuestionarios, progreso y certificados verificables. | Usuarios y permisos; recursos protegidos mediante Storage & Downloads. |
| Goldensite Events & Tickets | Eventos con aforo, entradas únicas, códigos QR y validación de acceso de un solo uso. | Generador QR y catálogo; los pagos necesitan su integración real. |
| Goldensite Support Desk | Tickets asignables, prioridades, estados, historial y plazos de atención. | Ampliación del buzón actual, sin otro sistema de contacto paralelo. |
| Goldensite S3 Connector | Almacenamiento remoto privado y entrega temporal desde proveedores compatibles con S3. | Adaptador para la biblioteca de archivos y DAM existentes. |

Antes de permitir instalar plugins subidos desde el panel, debe existir un flujo de paquetes autorizados: manifiesto y versión compatibles, validación de firma y checksum, rutas seguras, dependencias, respaldo y recuperación. Un archivo subido a esta biblioteca no recibe permisos para modificar Goldensite.
