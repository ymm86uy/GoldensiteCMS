# Goldensite Forum por el canal oficial

El adaptador de GoldenSiteCMS 1.0.24 permite distribuir el código de Forum por separado. Actualiza Owner y Comercial desde 1.0.23 con el parche firmado de cada edición. El foro queda disponible con su implementación incluida hasta que se instale un paquete independiente.

## Publicación e instalación

1. En Owner, desbloquea **Owner · licencias**. En **Módulos y extensiones → Canal oficial de extensiones**, adjunta el paquete de Forum, pega su firma y confirma con tu contraseña administrativa.
2. En Comercial 1.0.24, abre **Módulos y extensiones → Buscar extensiones oficiales** e instala **Goldensite Forum**.
3. Abre `/forum` y la pantalla de moderación en el panel. Si el foro estaba desactivado, puedes activarlo desde su ficha habitual.

El módulo conserva su identificador `forum`, sus rutas y permisos. Usa las mismas tablas, preferencias y archivos adjuntos. No se vuelve a ejecutar el instalador de sus tablas al importar el paquete.

## Recuperación

Desde el catálogo puedes volver al Forum incluido en el núcleo. Cuando existe un paquete anterior, también está disponible la recuperación de esa versión. Si falla la verificación de los archivos durante una petición, el sitio usa la implementación incluida en el núcleo y registra el motivo en el registro del servidor. La selección se aplica al código y mantiene los datos y el estado de activación.

## Crear una nueva entrega desde Owner

```sh
php tools/build-forum-package.php 1.0.1 Goldensite-Forum-1.0.1.zip
php tools/sign-extension.php Goldensite-Forum-1.0.1.zip
```

Este formato permite actualizar los archivos que ya componen Forum mediante un paquete completo firmado. Los cambios a las rutas del núcleo o al esquema del foro requieren un parche de GoldenSiteCMS. Publica únicamente paquetes revisados, ya que el código firmado se ejecuta con los permisos del CMS.
