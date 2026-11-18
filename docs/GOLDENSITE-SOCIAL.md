# GoldensiteCMS Social Network

## Arquitectura de la primera entrega

El módulo vive en `modules/GoldensiteSocial/`. El manifiesto lo registra en Owner y Comercial. El catálogo lo deja desactivado al instalar. Al activarlo, el enlace **Red social** abre `/social` y la API queda disponible para las cuentas activas del CMS. Al desactivarlo, las rutas de contenido dejan de servir datos y las publicaciones permanecen en la base.

- `Controller.php` recibe las operaciones REST y renderiza la página con el tema del CMS. `Store.php` usa PDO con parámetros y coordina las escrituras en hilos ocultos por moderación.
- `Access.php` reutiliza sesión, CSRF, límites por usuario e IP y permisos. El router ejecuta el WAF existente antes de cada solicitud. La vista limita la incrustación y las conexiones a su origen. La terminación HTTPS y HSTS corresponde al servidor.
- `Migrations/install.sql` crea cuatro tablas InnoDB. Los ecos, ondas, resonancias y presencia se vinculan a `users`. Al eliminar una cuenta se anonimiza la autoría y desaparecen sus estados efímeros y resonancias. La auditoría de moderación usa el registro del CMS.
- `Audio.php` guarda MP3, Ogg o WAV de hasta 8 MB en almacenamiento privado. Se valida el MIME detectado, se asignan nombres aleatorios y se comprueba la sesión y visibilidad antes de servir un archivo. Los respaldos de Release & Backup incluyen el almacén.
- Las regiones se eligen manualmente. Solo se publica el recuento agregado de una región con al menos tres cuentas, accesible a miembros. El mapa se actualiza cada 30 segundos, la presencia caduca a los 15 minutos y el mantenimiento borra los registros vencidos. El país asociado a un eco es opcional y persistente.
- El halo cuenta participaciones voluntarias distintas. El tiempo declarado se limita a 5–120 segundos y la UI interrumpe la medición al ocultar la pestaña. Es una señal de interacción informada por el cliente, sin valor probatorio sobre atención humana.

## Operación

Ejecuta `php database/migrate.php` en desarrollo, activa **GoldensiteCMS Social Network** desde **Módulos y extensiones** y abre la red. Para restringir publicaciones, selecciona **Roles con permiso social.publish** en el panel del módulo. Usa `social.moderate` para moderar ecos y ondas. Programa `tools/maintenance.php` para retirar presencia vencida. Utiliza una URL de sitio coherente con `APP_URL` y HTTPS en producción.

## Siguiente etapa

1. **Mapa y eventos.** Añadir un modelo de eventos y un servicio PostGIS opcional con identificadores del CMS y celdas geográficas aproximadas calculadas antes de enviar datos. Evaluar umbral de agregación, densidad y retención antes de incorporar proximidad. [ST_DWithin](https://postgis.net/docs/ST_DWithin.html)
2. **Tiempo real y medios.** Introducir WebSocket con credencial de conexión breve ligada a la sesión del CMS y Redis para difusión cuando exista demanda medible. Usar almacenamiento S3 privado con URLs de entrega temporales para objetos grandes y CDN según sus permisos. Añadir escenas 3D declarativas y evaluar audio espacial y vídeo en un entorno aislado.
3. **Afinidad y asistente.** Diseñar señales de ánimo explícitas, revocables y con vencimiento. Separar las preferencias de bienestar de las métricas públicas. El asistente futuro debe usar solo contenido autorizado y ofrecer controles de tiempo y consentimiento. La primera entrega no recoge voz, gestos ni señales biométricas y no llama a un proveedor de IA.

