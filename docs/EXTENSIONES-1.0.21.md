# Canal oficial de extensiones de GoldenSiteCMS

El núcleo 1.0.21 incorpora el canal de extensiones. Actualiza Owner y Comercial una vez para habilitarlo. A partir de ahí cada extensión tiene su propia versión.

## Publicar desde Owner

1. Prepara un ZIP de hasta 2 MB. Pon directamente en el ZIP `module.json`, `main.php` y, si necesitas una pantalla administrativa, `admin.php`.
2. Revisa el código y firma el ZIP en tu instalación de desarrollo Owner. La firma autoriza código PHP que se ejecutará con los permisos del CMS.

   ```bash
   php tools/sign-extension.php mi-extension.zip
   ```

3. Desbloquea Owner y abre **Módulos y extensiones → Canal oficial de extensiones**. Adjunta el ZIP, pega el contenido de `.signature.txt` y confirma tu contraseña administrativa. **Instalar y publicar extensión** instala esa versión en Owner y la ofrece a Comercial.
4. Para publicar una mejora, conserva el mismo identificador y sube la versión del complemento. Guarda una copia del código y del paquete firmado.

## Instalar desde Comercial

En **Módulos y extensiones**, pulsa **Buscar extensiones oficiales** y elige **Descargar e instalar**. GoldenSiteCMS verifica la firma, el ZIP y la versión mínima del núcleo. Luego muestra la extensión en el catálogo. La primera instalación queda desactivada. Una extensión PRO requiere una licencia para activarse. Una actualización conserva la activación previa cuando la política de licencia lo permite.

El sitio Comercial debe poder conectarse por HTTPS a `goldensitecms.com`. El PHP de ambos sitios necesita escritura en `storage` y acceso a cURL, ZIP y OpenSSL.

## Contrato del paquete

```json
{
  "id": "addon-mi-plugin",
  "name": "Goldensite Mi Plugin",
  "description": "Descripción para el catálogo",
  "version": "1.0.0",
  "min_core": "1.0.21",
  "api": 1,
  "pro": false,
  "admin": false
}
```

`main.php` y el archivo opcional `admin.php` deben devolver una función anónima que produzca el HTML del contenido. El CMS aplica su presentación, permisos de acceso, comprobación de licencia y protección CSRF para solicitudes POST. El complemento es responsable de validar sus entradas y de autorizar cada operación sobre sus datos.

```php
<?php
return static fn (): string => '<h1>Mi extensión de Goldensite</h1>';
```

La ruta pública es `/extensions/<id>`. Si declaras `"admin": true`, la ruta administrativa aparece como `/admin/plugins/<id>` bajo el alias de tu consola. Esta versión del formato admite esos puntos de entrada. Los módulos existentes del núcleo no se convierten automáticamente en paquetes y los ZIP con migraciones de base de datos o dependencias de terceros quedan fuera de este formato.
