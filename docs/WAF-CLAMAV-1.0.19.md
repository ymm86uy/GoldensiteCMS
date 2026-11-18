# GoldenSiteCMS 1.0.19

## Instalación del parche

En **Release Manager & Advanced Backup**, usa el ZIP correspondiente a tu edición y a la versión instalada en el dashboard. Adjunta el ZIP, pega la firma del archivo que lleva el mismo nombre seguido de `.signature.txt` y aplica la actualización preparada. La 1.0.19 incorpora también la edición de la portada y el guardado de mantenimiento y cabeceras incluidos en la 1.0.18.

## Bloqueos persistentes

El WAF incorpora de inmediato las IP públicas de detecciones de alta confianza a **Reglas de acceso**. Allí se ve el motivo y la referencia del evento, y se puede retirar el bloqueo. Las detecciones históricas conservan su registro original. Un nuevo evento grave puede volver a bloquear una IP retirada.

Los límites de frecuencia, errores de tamaño o método y reglas manuales por ruta siguen resolviéndose por petición. Solo se usa la dirección que el servidor entrega en `REMOTE_ADDR`. Si existe un proxy inverso, configura primero el módulo de IP real del servidor con proxies de confianza. Las redes internas y las sesiones administrativas autorizadas quedan fuera del bloqueo automático permanente. Si hay una IP administrativa fija, puede excluirse del bloqueo automático permanente con `GOLDENSITE_WAF_AUTOBAN_EXEMPT` en el `.env` privado (IPs o CIDR separados por coma). Las reglas de detección y bloqueos manuales siguen vigentes.

## Motor antivirus y firmas

1. En AlmaLinux 9, usa el repositorio EPEL, según el [manual de instalación de ClamAV](https://docs.clamav.net/manual/Installing/Packages.html). Con EPEL habilitado, instala `clamd` y `clamav-freshclam`.
2. Configura FreshClam para usar la distribución predeterminada oficial. Programa su servicio de actualización y verifica la primera descarga antes de arrancar ClamD. Configura `NotifyClamd` con el archivo de configuración de la instancia o verifica que la instancia recargue las bases tras una actualización. [Actualización oficial de firmas](https://docs.clamav.net/manual/Usage/SignatureManagement.html).
3. Configura **un socket Unix local** para ClamD y concede al usuario de PHP-FPM únicamente permiso para conectarse al socket. Mantén SELinux activo y verifica su política si impide la conexión. Evita abrir el puerto de ClamD a Internet. Configura `StreamMaxLength` y `MaxFileSize` con al menos 20 MB. Habilita `AlertExceedsMax yes` para tratar análisis incompletos por límites como detecciones. Ajusta los límites de descompresión y los trabajadores a la memoria del VPS. [Configuración oficial](https://docs.clamav.net/manual/Usage/Configuration.html).
4. En el `.env` **privado** del CMS, añade la ruta real del socket de tu instancia. Este ejemplo se debe adaptar al socket configurado.

   ```dotenv
   GOLDENSITE_CLAMD_ENDPOINT="unix:///run/clamd.scan/clamd.sock"
   ```

5. Abre **WAF y análisis de archivos → Analizador de archivos**. Comprueba el indicador **CLAMAV ACTIVO** y la revisión de firmas que devuelve el servicio. Verifica una subida normal y una detección con la muestra oficial de prueba de antivirus en una instalación aislada. Prueba también que una caída de ClamD rechaza la carga y que el sitio sigue respondiendo para navegación normal.

El CMS escanea las cargas multipart de sus puntos de entrada PHP antes de llegar a los controladores, incluidas las de páginas o módulos nuevos. ClamD recibe un flujo local del archivo temporal de PHP, sin crear otra copia en la aplicación. El CMS conserva el archivo únicamente cuando el análisis y las validaciones del módulo permiten guardarlo. La carga admite un máximo agregado de 20 MB y diez archivos, sujeta también a los límites del servidor. Si ClamD está configurado y no completa la revisión, la carga se rechaza. Sin conexión configurada, el panel lo indica y conserva el diagnóstico manual.

Las cargas directas por FTP, Webuzo o SSH no pasan por PHP del CMS. Para controlar esos canales se requiere protección del servidor. El WAF de aplicación actúa sobre tráfico que llega al CMS. La mitigación de saturación debe actuar antes, en el servidor web o el proveedor.
