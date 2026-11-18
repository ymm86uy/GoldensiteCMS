# Extensiones con datos en GoldenSiteCMS 1.0.23

Aplica el parche de tu edición desde Release & Backup. Usa el parche que empiece con la versión exacta que figure instalada en el panel. Adjunta su ZIP y pega el contenido del archivo de firma correspondiente.

## Declarar tablas propias

Conserva el manifiesto y los puntos de entrada del formato anterior. Si la extensión requiere tablas, añade `"database": true` y `"min_core": "1.0.23"` a `module.json` y coloca un `schema.json` en la raíz del ZIP. Por ejemplo, para la extensión `addon-reservas`:

```json
{
  "version": 1,
  "tables": {
    "entries": {
      "title": "string",
      "notes": "text",
      "capacity": "integer",
      "visible": "flag",
      "starts_at": "datetime"
    }
  }
}
```

La tabla se llama `gs_ext_addon_reservas__entries`. Desde el código puedes obtener su nombre con `App\Core\ExtensionSchema::tableName('addon-reservas', 'entries')`. El CMS crea `id` como clave primaria y las demás columnas aceptan valores nulos. Cada versión puede declarar el esquema acumulado. Crear tablas y añadir columnas es compatible con los datos existentes. Las operaciones de borrado, cambio de tipo, cambio de nombre o SQL libre quedan fuera de este formato.

El ZIP completo se firma con `php tools/sign-extension.php paquete.zip`. Owner lo publica en **Módulos y extensiones**. Comercial requiere conectividad HTTPS a la sede para descargarlo. El usuario de base de datos de la instalación necesita permisos para crear y alterar las tablas de esa misma base.

Si una instalación falla antes de registrar el código, la versión previamente activa sigue en uso. Puede quedar un cambio aditivo ya aplicado. Volver a instalar el mismo paquete reintenta los cambios pendientes sin duplicar tablas ni columnas.

## Recuperar la versión anterior

El catálogo conserva una versión anterior recuperable. Abre sus detalles, revisa el número de versión y confirma con tu contraseña administrativa. En Owner se requiere además desbloquear el panel Owner.

La recuperación comprueba la firma y huella del ZIP anterior antes de restaurar sus archivos y mantiene la activación. Las tablas, columnas y filas quedan preservadas. Si eres Owner, la extensión queda retirada del canal hasta que publiques otra vez la última versión firmada o una posterior. En Comercial el catálogo aún puede ofrecer la versión que el sitio Owner siga publicando. La recuperación preserva la categoría de licencia y evita que el formulario normal sustituya el paquete por una versión antigua.

En Owner también puedes retirar un paquete del canal sin recuperar otro código. La retirada detiene futuras descargas. Cada sitio que ya instaló el módulo conserva su copia.

La migración de Forum requiere un adaptador para mantener sus rutas, permisos y tablas existentes. Conserva su integración actual hasta verificar la transición sobre una copia con datos.
