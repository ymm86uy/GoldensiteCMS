# Observación CSP de GoldenSiteCMS

Disponible desde 1.0.39 en Owner y Comercial, dentro de **WAF y análisis de archivos → Observación CSP**. No es otro módulo ni requiere un proveedor externo. Está desactivada al instalar o actualizar, salvo que el administrador ya la haya habilitado.

Un superadministrador puede activarla o desactivarla confirmando su contraseña actual. Se mantienen CSRF, el permiso de acceso al WAF y un límite de cinco cambios/intentos por cuenta cada quince minutos. La configuración y sus cambios quedan en los servicios actuales de ajustes y actividad.

## Qué mide

La cabecera `Content-Security-Policy-Report-Only` ensaya `script-src 'self'`, junto con las restricciones actuales de objetos, base y marcos. Detecta tipos de recursos que necesitarían revisión antes de aplicar una política más estricta. No modifica la cabecera CSP que se está aplicando ni añade autorización automática a scripts.

Se usan `Reporting-Endpoints` y `report-to`, con `report-uri` como compatibilidad para navegadores anteriores. Referencias: [MDN, CSP Report-Only](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy-Report-Only), [MDN, report-to](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/report-to).

El receptor es `/_security/csp-report` del origen público configurado en `APP_URL`. El nombre, esquema y puerto deben corresponder al sitio visitado, incluyendo el uso de `www`. No se habilita CORS ni se consulta una URL entregada por el navegador. Solo esa ruta POST está exenta del token CSRF administrativo porque los informes automáticos no pueden proporcionarlo. El WAF y sus bloqueos siguen aplicándose a la petición.

## Privacidad y límites

El receptor procesa cuerpos de hasta 16 KiB, ocho elementos por lote, veinte peticiones por dirección/minuto y sesenta por instalación/minuto. Admite únicamente los dos tipos de contenido de informes CSP, documentos del origen configurado, disposición `report` y un vocabulario fijo de directivas. Rechaza Origin externo y peticiones declaradas cross-site/same-site. Si falta Origin, valida igualmente el origen del documento declarado, que sigue siendo un dato no autenticado.

La tabla `goldensite_csp_reports` guarda únicamente día UTC, área (público, administración, acceso/cuenta), directiva, tipo de recurso, contador y fecha UTC del último aviso. Se descartan direcciones IP, usuarios, URL, consultas, dominios de recursos, remitentes y muestras de código del cuerpo recibido. Los límites de frecuencia usan el servicio existente con nombres de archivo derivados por hash; los registros independientes de HTTP, sesiones y WAF mantienen su propia política.

Se conservan catorce días, con eliminación al recibir informes y mediante el trabajador de mantenimiento existente. La clave compuesta y el vocabulario fijo limitan el número de filas; los contadores se acotan a un millón por grupo/día. Desactivar detiene los nuevos informes y retira las cabeceras de observación en la siguiente respuesta GET; los datos previos siguen sujetos a retención.

## Cómo interpretar el panel

Los contadores son avisos recibidos, no visitantes, ataques únicos ni una cobertura completa del tráfico. Pueden existir duplicados, avisos falsificados, extensiones de navegador y pérdida por límites de recepción, incompatibilidad o falta de acceso al receptor. No hay bloqueo de IP ni clasificación de ataques a partir de estos avisos.

Para encontrar el archivo o código concreto, reproduce la página del área indicada y revisa los avisos CSP de la consola del navegador. El servidor no conserva esas rutas ni fragmentos para evitar registrar tokens o información personal. Que no haya avisos no demuestra que la política estricta sea compatible.

Una siguiente fase podrá autorizar scripts mediante nonces o hashes, después de verificar temas, editores, anuncios y demás integraciones. Esta versión no aplica ese endurecimiento automáticamente.
