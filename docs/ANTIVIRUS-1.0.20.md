# Políticas de carga y antivirus en GoldenSiteCMS 1.0.20

Cada instalación configura su protección en el archivo privado `.env`. La política y el estado de verificación aparecen en **WAF y análisis de archivos → Analizador de archivos**.

| Infraestructura | Configuración | Resultado |
| --- | --- | --- |
| VPS Linux con ClamAV | `GOLDENSITE_UPLOAD_POLICY="required"` y el socket Unix local de ClamD | El CMS exige un resultado limpio antes de guardar. |
| Windows o entorno con ClamD en la misma red local del proceso | `GOLDENSITE_UPLOAD_POLICY="required"` y, por ejemplo, `GOLDENSITE_CLAMD_ENDPOINT="tcp://127.0.0.1:3310"` | Conexión solo por loopback. Mantén ClamD inaccesible desde Internet y usa este modo únicamente en un servidor de confianza. |
| Alojamiento administrado | `GOLDENSITE_UPLOAD_POLICY="provider"` | El administrador depende del proveedor. Confirma con él que su antivirus inspeccione las subidas **antes** de guardarlas y que pueda rechazar archivos. GoldenSiteCMS muestra esta protección como sin verificar. |
| Alojamiento sin escaneo disponible | `GOLDENSITE_UPLOAD_POLICY="disabled"` | El CMS rechaza las subidas, incluidas las del panel. La navegación y los formularios sin archivos siguen disponibles. |
| Instalación existente | `GOLDENSITE_UPLOAD_POLICY="auto"` o sin esta variable | Conserva la configuración anterior. Con un endpoint usa ClamAV. Sin endpoint, el panel indica que falta escaneo automático. |

Un valor inválido o una falla del motor con política obligatoria rechazan las subidas. Los cambios afectan las cargas procesadas por GoldenSiteCMS. Las transferencias por FTP, SSH y gestores externos requieren controles propios del servidor.

## Ubicación de archivos y firmas

- Mantén `upload_tmp_dir` en un directorio privado de PHP fuera del árbol del CMS. Con ClamAV obligatorio, GoldenSiteCMS rechaza archivos temporales ubicados dentro de ese árbol.
- Configura `TemporaryDirectory` de ClamD en un directorio privado del sistema, separado de las firmas y accesible solo al usuario del motor. Guarda las firmas con FreshClam en el directorio de datos de ClamAV.
- GoldenSiteCMS trabaja por flujo y conserva solo metadatos de la detección. Si tu organización necesita retener una muestra, usa un almacén separado del CMS, de la raíz web y de sus respaldos, con acceso limitado a los responsables de seguridad.
- Revisa si existe cuarentena histórica en instalaciones antiguas. Aísla sus archivos fuera del CMS durante una intervención de mantenimiento. Conserva las evidencias según las reglas de retención de tu organización.

[Configuración de ClamAV y FreshClam](https://docs.clamav.net/manual/Usage/Configuration.html).

## Actualizar desde 1.0.19

Usa el ZIP y la firma **1.0.19 → 1.0.20** correspondientes a tu edición en **Release Manager & Advanced Backup**. Después de aplicar el parche, abre el analizador para comprobar la política activa. La configuración existente de ClamAV se interpreta como obligatoria si ya tiene un endpoint.
