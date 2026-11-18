# GoldenSiteCMS 1.0.22

Instala el parche correspondiente a tu edición desde **Release & Backup** sobre una instalación 1.0.21. Adjunta el ZIP cuyo nombre termina en `-update.zip` y pega su firma. Verifica y prepara el paquete, y después pulsa **Instalar actualización**. El instalador completo es para sitios nuevos.

## Comprobar la instalación

El archivo `*-manifest.json` de cada edición se genera desde su instalador completo. Copia el manifiesto al VPS a una carpeta privada y ejecuta desde la raíz del CMS:

```sh
php tools/release-integrity.php verify /ruta/privada/al-manifest.json
```

El diagnóstico muestra los archivos faltantes, cambiados y el código adicional. Revisa las diferencias antes de retirar un archivo. La herramienta no modifica el sitio. En Webuzo sustituye `php` por la ruta del PHP de la aplicación si el ejecutable predeterminado usa otra versión.

## Revisión editorial

1. Crea una página o un artículo y guarda como borrador. Los botones de secciones insertan plantillas editables en el contenido y la vista previa permite comparar tres anchos.
2. Pulsa **Enviar a revisión**. Una cuenta con permiso de revisión puede aprobar el borrador guardado. Si se guarda nuevamente el contenido, envíalo otra vez a revisión.
3. Una cuenta con el permiso de publicación puede programar o cancelar una fecha. La hora del formulario es UTC. El sitio conserva su flujo directo para cuentas con permiso de publicación.
4. El trabajador `tools/maintenance.php` publica las tareas cuyo plazo se cumplió. Configura su ejecución periódica con el usuario de la aplicación. Para una periodicidad de cinco minutos en Webuzo, usa los campos `*/5` para minutos y `*` para los demás y el comando de tu instalación. Por ejemplo, para el VPS Owner actual:

   ```sh
   cd /home/wisinowner/public_html && /usr/local/apps/php84/bin/php tools/maintenance.php
   ```

5. Comprueba la última ejecución en **Site Doctor** o en el dashboard. Antes de automatizarlo en producción, revisa la programación de respaldos y la retención de registros existente, porque se aplican al ejecutar el mismo trabajador.

## Respaldo de recuperación

El procedimiento de ensayo usa un ZIP de Release & Backup y una carpeta y base de datos vacías y privadas. El ensayo crea una cuenta temporal en la copia restaurada, comprueba el acceso al panel local y deja un informe para registrar en Site Doctor. Ejecuta `php tools/recovery-drill.php` para ver los requisitos de la base de datos aislada y los argumentos de `run`. Después, `php tools/recovery-drill.php record /ruta/privada/recovery-report.json` registra el informe si el ZIP corresponde a uno de los respaldos locales.

## Recursos de extensiones

Los paquetes pueden declarar `"assets": ["style.css", "script.js"]` y colocar los archivos declarados directamente en el ZIP. Los dos recursos son opcionales. Usa `"min_core": "1.0.22"` o posterior. Una extensión activa puede referenciar los recursos en `/extensions/<id>/assets/style.css` y `/extensions/<id>/assets/script.js`. Se verifica la integridad antes de servirlos. El publicador sigue firmando los paquetes con su identidad Owner. La primera instalación conserva el paso separado de activación.

Las migraciones de plugins y la vuelta a una versión de plugin con datos propios necesitan una entrega posterior. Forum sigue en su integración actual.
