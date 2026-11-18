# Goldensite Forum

Módulo nativo y gratuito en Owner y Comercial. La instalación lo deja desactivado. Se habilita en **Módulos y extensiones**. El panel **Moderación** permite crear la primera categoría y subforo. El foro público abre en `/forum`. Si el sitio usa una ruta administrativa alternativa, el enlace del catálogo usa la ruta de la instalación.

## Arquitectura

| Componente | Responsabilidad |
| --- | --- |
| `module.json` y `routes.php` | Registro en el catálogo y rutas en el router del CMS. Ambas ediciones incluyen el mismo código del foro. |
| `Controllers/` y `Views/` | Formulario, hilos, votación, seguimiento, avisos y moderación. Las vistas usan el tema y la sesión de GoldenSiteCMS. |
| `Models/` | Consultas PDO preparadas. Las mutaciones que afectan a un hilo se coordinan con un bloqueo sobre el hilo. |
| `Migrations/install.sql` | Tablas InnoDB aditivas. Los instaladores cargan la migración después de la tabla de usuarios. Los parches incluyen el mismo SQL. Las tablas comparten la base del CMS y se conservan al desactivar el módulo. |
| `Notifications.php` | Avisos internos y trabajos de correo. El editor no tiene que esperar la entrega SMTP. |
| `Attachments.php` | Guarda adjuntos bajo `storage/forum/`. Cada descarga comprueba que el hilo y la respuesta sigan visibles. |

El middleware WAF se ejecuta antes de las rutas del foro. Las escrituras requieren una sesión activa y CSRF. Publicar aplica cinco intentos por cuenta y 25 por IP en un minuto. Moderación usa el permiso `forum.moderate` de Identity & Access. Las medallas de reputación se derivan de reacciones y soluciones. La insignia de moderación refleja el rol asignado. Una insignia de reputación nunca concede privilegios.

La URL de un hilo usa su ID y un slug estable. Los listados usan cursores por actividad e ID. Las respuestas usan un cursor por ID. Los hilos incluyen Open Graph y el tipo `DiscussionForumPosting`. Los borrados son reversibles desde moderación. Al fusionar una discusión, el enlace anterior redirige al hilo destino. Las encuestas permanecen separadas para conservar opciones y votos. [Schema.org](https://schema.org/DiscussionForumPosting)

El editor ofrece Markdown básico, vista previa, bloques de código con resaltado de palabras clave, emoticonos y un adjunto por publicación. Admite JPEG, PNG, WebP y PDF hasta 5 MB. Las imágenes se verifican por tipo y dimensiones. Los PDF se descargan como adjuntos. El ZIP de respaldo incluye `storage/forum/`. [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)

## Identidad y permisos

En Moderación, **Identidad y permisos** permite definir el nombre visible, la descripción, las fuentes del cuerpo y de los títulos, los tamaños y el estilo de portada. La portada predeterminada se genera con el estilo elegido y un monograma. Puedes reemplazarla y añadir un logo con imágenes JPEG, PNG o WebP de hasta 4 MB. Las imágenes se convierten a WebP y se sirven desde el almacén privado del foro.

La publicación puede abrirse a todas las cuentas activas o limitarse al permiso `forum.participate` de Identity & Access. Este permiso permite participar sin dar acceso administrativo. La edición de la identidad requiere moderación y `appearance.write`, o la cuenta administradora principal.

## Operación

- En Identity & Access, asigna `forum.moderate` a quienes deban administrar el foro.
- En **Notificaciones**, cada miembro puede habilitar correos por menciones y respuestas a sus hilos. En cada hilo puede habilitar avisos internos o también correos por seguimiento.
- Para entrega SMTP se requiere una URL HTTPS en `APP_URL`, correo configurado y el trabajador existente `tools/module-worker.php` programado en el servidor.
- Para migrar una instalación existente, usa un paquete firmado de la edición instalada cuyo origen coincida con la versión instalada. Para una copia local de desarrollo, desde la raíz del CMS ejecuta `php database/migrate.php` y activa el foro en el catálogo.
- Revisa desde el navegador la vista móvil, navegación por teclado y la entrega SMTP en un buzón de ensayo antes de publicarlo.
