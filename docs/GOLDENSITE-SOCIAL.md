# GoldensiteCMS Social Network

## Arquitectura de la primera entrega

El módulo vive en `modules/GoldensiteSocial/`. El manifiesto lo registra en Owner y Comercial. El catálogo lo deja desactivado al instalar. Al activarlo, el enlace **Red social** abre `/social` y la API queda disponible para las cuentas activas del CMS. Al desactivarlo, las rutas de contenido dejan de servir datos y las publicaciones permanecen en la base.

- `Controller.php` recibe las operaciones REST y renderiza la página con el tema del CMS. `Store.php` usa PDO con parámetros y coordina las escrituras en hilos ocultos por moderación.
- `Access.php` reutiliza sesión, CSRF, límites por usuario e IP y permisos. El router ejecuta el WAF existente antes de cada solicitud. La vista limita la incrustación y las conexiones a su origen. La terminación HTTPS y HSTS corresponde al servidor.
- `Migrations/install.sql` crea cuatro tablas InnoDB. Los ecos, ondas, resonancias y presencia se vinculan a `users`. Al eliminar una cuenta se anonimiza la autoría y desaparecen sus estados efímeros y resonancias. La auditoría de moderación usa el registro del CMS.
- `Audio.php` guarda MP3, Ogg o WAV de hasta 8 MB. `Image.php` admite JPEG, PNG y WebP de hasta 4 MB y 8 megapíxeles, y vuelve a codificarlos como WebP para eliminar metadatos, incluido el GPS incrustado. Ambos medios usan almacenamiento privado y se sirven tras comprobar la sesión y la visibilidad. Los respaldos de Release & Backup incluyen el almacén.
- El mapa muestra hasta 60 ecos recientes con ubicación compartida. Las burbujas cercanas se agrupan y cada eco se puede abrir desde el mapa o la lista. La presencia temporal sigue siendo independiente y agregada, con un mínimo de tres cuentas por país y caducidad de 15 minutos. El mapa se actualiza cada 30 segundos.
- La ubicación se elige por eco. **Sin ubicación** es la opción inicial y deja el eco en el listado. **Solo país** sitúa la burbuja en un punto de referencia común del país seleccionado. **Zona aproximada** pide permiso en HTTPS y convierte la posición en una celda de unos 10 km de ancho (aproximadamente tres millas desde el centro a un lado). Se envían únicamente los índices de la celda y el país seleccionado. El autor puede reducir la precisión a país o quitar la ubicación después de publicar. Una zona sigue siendo información geográfica que otros miembros pueden ver.
- El halo cuenta participaciones voluntarias distintas. El tiempo declarado se limita a 5–120 segundos y la UI interrumpe la medición al ocultar la pestaña. Es una señal de interacción informada por el cliente, sin valor probatorio sobre atención humana.

## Operación

Ejecuta `php database/migrate.php` en desarrollo, activa **GoldensiteCMS Social Network** desde **Módulos y extensiones** y abre la red. Para restringir publicaciones, selecciona **Roles con permiso social.publish** en el panel del módulo. Usa `social.moderate` para moderar ecos y ondas. Programa `tools/maintenance.php` para retirar presencia vencida. Utiliza una URL de sitio coherente con `APP_URL` y HTTPS en producción.

## Siguiente etapa

1. **Eventos.** Evaluar un modelo para eventos de la comunidad y reglas de agregación y retención para un mapa de mayor densidad.
2. **Tiempo real y medios.** Introducir WebSocket con credencial de conexión breve ligada a la sesión del CMS y Redis para difusión cuando exista demanda medible. Usar almacenamiento S3 privado con URLs de entrega temporales para objetos grandes y CDN según sus permisos. Añadir escenas 3D declarativas y evaluar audio espacial y vídeo en un entorno aislado.
3. **Afinidad y asistente.** Diseñar señales de ánimo explícitas, revocables y con vencimiento. Separar las preferencias de bienestar de las métricas públicas. El asistente futuro debe usar solo contenido autorizado y ofrecer controles de tiempo y consentimiento. La primera entrega no recoge voz, gestos ni señales biométricas y no llama a un proveedor de IA.

