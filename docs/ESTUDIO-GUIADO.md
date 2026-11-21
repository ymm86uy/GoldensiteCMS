# Crear un sitio con el Estudio de composición

La edición Comercial incorpora desde 1.0.40 un recorrido visual dentro del estudio existente, en `/admin/ai-builder`. No requiere instalar otro constructor. La edición Owner conserva su entrada avanzada habitual.

## Recorrido

1. Elige **Estudio creativo** o **Consultoría**. Son composiciones distintas, con una dirección clara y otra oscura.
2. Define nombre, color y tipografía. Selecciona un logotipo y una imagen desde la biblioteca o súbelos con el cargador nativo. Si no eliges imagen, se usa una composición gráfica local.
3. Edita la presentación, servicios, nosotros y contacto. Puedes activar secciones y cambiar su orden con las flechas, también usando el teclado.
4. Guarda el borrador. La portada publicada permanece vigente hasta confirmar una publicación.
5. Revisa escritorio, tableta y móvil. Un administrador completo puede publicar confirmando su contraseña actual.

Esta primera entrega compone una portada de una página, con navegación entre secciones y enlaces al contacto existente. Las páginas internas se siguen gestionando desde Páginas, enlazado en el estudio. No crea automáticamente documentos internos ni sustituye sus URL.

## Módulos y medios existentes

La sección de contacto permite elegir formularios activos de Goldensite Form Builder. La portada guarda su código corto; el módulo genera el formulario y su token en cada visita. No se persiste un token administrativo en el documento publicado. En la vista previa se muestra una indicación del formulario, con envíos y scripts bloqueados.

Las imágenes pasan por el antivirus global y `Uploader`/`UploadGuard`, como las demás cargas del sitio. Se aceptan imágenes locales de hasta 5 MB. La protección del servidor puede rechazar o impedir una carga; el editor no la omite. Las imágenes nuevas quedan en la misma biblioteca, con nombres generados. Las cargas no guardan ni publican automáticamente el borrador.

No se activan módulos, cambian licencias ni incorporan proveedores externos. Los enlaces de acción admiten rutas internas y HTTPS. El logotipo y la imagen principal deben resolverse dentro del almacenamiento público permitido; no se admiten URL externas ni recorridos de directorios para imágenes.

## Borradores, código e historial

Se conservan el editor avanzado y los cuatro generadores anteriores. Si existe un borrador avanzado, el recorrido visual exige confirmar que deseas sustituir ese borrador; no convierte ni reemplaza silenciosamente el HTML existente.

La composición utiliza `builder_draft_html` y `builder_draft_spec`. La publicación guarda `builder_published_spec` vinculado por SHA-256 al HTML publicado, para mantener su tipografía incluso si cambia un borrador. Se reutiliza la tabla `goldensite_builder_versions`, ampliada de forma aditiva con `composition_json` nullable. Las versiones previas sin metadatos siguen siendo recuperables como HTML.

Recuperar una versión la convierte en borrador y no cambia el sitio público. Para una versión guiada se recuperan también sus campos y orden de secciones. Si se edita manualmente su HTML y deja de coincidir con la composición registrada, se conserva como documento avanzado, sin atribuirle unos campos visuales que ya no lo describen.

## Protecciones y límites

Todos los cambios mantienen autenticación, permisos y CSRF. El guardado guiado exige la revisión vigente y rechaza sobrescrituras desde otra pestaña. Solo un administrador completo publica; el modo guiado siempre usa publicación restringida sin habilitar scripts de confianza.

La vista previa usa el mismo renderizador del diseño. Está en un iframe sin privilegios, con scripts y envíos bloqueados y acceso limitado a imágenes públicas locales. La previsualización está limitada a 60 solicitudes por cuenta/minuto y la subida a 30 por cuenta/hora, además de los límites del WAF. No hay autoguardado: el estado indica cambios pendientes hasta que se guarda o publica y el navegador avisa al salir con cambios.

Las capacidades de galerías, reservas, eventos y otros módulos se mantienen en sus implementaciones actuales. Esta entrega no los replica como secciones independientes ni aplica cambios al servidor de producción.
