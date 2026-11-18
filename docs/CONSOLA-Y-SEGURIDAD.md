# Consola Goldensite — revisión del 4 de noviembre de 2026

## Identidad y operación

La consola tiene una identidad visual propia: grafito, lima y violeta, marca geométrica, navegación numerada, paneles de actividad y composición adaptable a móvil. Los estilos se sirven desde archivos locales. Las páginas anteriores de módulos comparten la nueva estructura administrativa; sus formularios internos conservan sus funciones.

El dashboard consulta usuarios, sesiones, publicaciones, mensajes, módulos habilitados y eventos del WAF de la base real. Actualiza las métricas cada 30 segundos e informa si falla la actualización. El gráfico representa visitas registradas localmente, sin estimaciones. La tabla de sesiones permite revocar un acceso y exige autenticarse de nuevo en su siguiente solicitud. No expone cookies ni contraseñas.

Se reparó una tabla local de sesiones sin identificador único: acumulaba filas repetidas. La migración conserva una copia privada en `storage/schema-backups`, mantiene la fila más reciente y añade la restricción única. No cambia contraseñas ni borra contenido editorial.

## Temas y persistencia

Los 21 estilos cromáticos usan un catálogo común. Cada estilo conserva su propio perfil de colores, bordes, efectos y CSS en `settings.theme_profiles`. Activar otro estilo invalida las cachés y aplica su perfil en las páginas públicas; volver al anterior recupera sus ajustes. El personalizador rechaza cambios enviados desde una pestaña cuyo tema ya dejó de estar activo. Restaurar afecta únicamente al perfil seleccionado.

Activar un tema selecciona la portada estándar y desactiva la portada compuesta con IA, sin borrar su HTML guardado. Las personalizaciones locales anteriores se migraron al estilo que estaba activo. El catálogo muestra los colores efectivos del perfil guardado. Son 21 paletas con personalización, no 21 productos independientes con plantillas de contenido diferentes.

## Configuración

Los formularios validan antes de guardar y actualizan sus campos de forma atómica. Incluyen identidad pública, idioma del contenido, zona horaria, ruta alternativa del panel, mantenimiento HTTP 503, cabeceras, correo SMTP con TLS, caché y respaldos. La ruta `/admin` sigue disponible y requiere los mismos permisos. Cambiar idioma no traduce el contenido existente.

La contraseña SMTP se cifra con AES-256-GCM usando una clave derivada de la identidad privada de la instalación. El campo se presenta vacío y permite conservarla o eliminarla explícitamente. La comprobación SMTP verifica conexión y certificado TLS; no autentica al usuario ni envía mensajes. Las automatizaciones de correo usan PHPMailer y esta configuración. No se ha probado el envío con un proveedor real del usuario.

Publicar scripts de integración y restaurar SQL exige confirmar la contraseña actual. Los scripts públicos se insertan cuando existe consentimiento opcional `all`; no se incorporan a las páginas de acceso o administración. Los respaldos SQL quedan fuera de la raíz pública y se descargan mediante una acción autenticada con CSRF. Guarda también `.env`, claves y archivos multimedia por separado.

## Defensa de solicitudes

El motor PHP `NX-WAF-2026.11.04` normaliza parámetros y detecta patrones de SQLi, XSS, inclusión de archivos, ejecución de comandos, entidades XML y cabeceras manipuladas. Rechaza rutas privadas, recorridos de directorios, métodos no admitidos y solicitudes excesivas. Son reglas locales acotadas, no un catálogo completo de vulnerabilidades ni una garantía contra ataques desconocidos. El análisis de contenido cubre parámetros GET y formularios POST; no es una inspección general de todos los cuerpos JSON o multipart binarios.

Los modos de bloqueo y observación se guardan en la base. Los límites de recursos, rutas, métodos y bloqueos manuales permanecen activos en observación. El editor autenticado con CSRF puede guardar contenido HTML legítimo. No se analizan valores de contraseñas, secretos o firmas como si fueran contenido editorial.

Cada detección recibe referencia, reglas, campos y decisión. No se guardan cuerpos de formulario ni cadenas de consulta en el registro. Hay filtros, paginación y exportación CSV con protección frente a fórmulas. La escritura de eventos está limitada para reducir abuso del registro; no registra cada intento de una ráfaga ilimitada.

Se admiten bloqueos IPv4/IPv6 y redes CIDR con vencimiento; el panel rechaza bloquear la conexión propia. La identidad de red usa `REMOTE_ADDR` y no confía en cabeceras enviadas por el cliente. Si el VPS usa un proxy o CDN, configura sus direcciones de confianza en el servidor web; no habilites confianza general en `X-Forwarded-For`.

El análisis de archivos es heurístico, limitado a 20 MB. Aísla muestras sospechosas con nombres aleatorios `.bin` fuera de la web. No reemplaza un antivirus ni certifica archivos como inocuos.

## Operación en el VPS

El registro distingue el evento histórico de las reglas manuales vigentes que coinciden con su dirección IP. Usa **Retirar bloqueo** para quitar una regla concreta. Si eliges una red CIDR, retiras el bloqueo de esa red completa. El evento queda en el registro y las reglas de detección siguen en vigor.

Los mapas del WAF y del dashboard se actualizan cada 15 segundos con la ventana de actividad de los últimos 30 minutos. El dashboard agrega cuentas autenticadas por país, contando una vez cada cuenta según su sesión más reciente. El WAF ubica el país aproximado de la IP de cada evento que ya registra. Las IP sin país estimado aparecen en el recuento sin marcador y la ubicación de una VPN o un proxy puede corresponder al servidor de salida.

La resolución se hace en el propio servidor con [DB-IP Country Lite](https://db-ip.com/db/lite.php) (CC BY 4.0, edición noviembre de 2026). La cartografía procede de [Natural Earth](https://www.naturalearthdata.com/about/terms-of-use/). Para renovar la base, sustituye `app/Geo/dbip-country-lite.mmdb` por la edición Country Lite MMDB actual a través de una actualización firmada.

La protección PHP empieza cuando una solicitud llega al CMS. Para añadir ModSecurity con OWASP Core Rule Set delante de PHP en Webuzo, revisa **Security → ModSecurity Configuration** y las reglas del proveedor OWASP en **ModSecurity Rules**. Comprueba primero si el Apache administrado por Webuzo ya carga el motor. Usa el instalador o gestor de Webuzo para mantener su configuración y sus actualizaciones.

Empieza en detección y verifica en el registro una solicitud legítima de cada flujo importante, entre ellos el login, la publicación con el editor, la carga de ZIP firmados y la descarga del canal comercial. Ajusta las excepciones necesarias por dominio, ruta y regla. Valida la configuración con el `apachectl configtest` del Apache de Webuzo y comprueba la respuesta de las rutas antes de activar el bloqueo. El panel del CMS muestra **NO VERIFICADO** hasta contar con una comprobación de esa capa externa. La configuración orientativa está en `deployment/modsecurity-crs.conf.example`.

Mantén sistema, servidor web, PHP y dependencias actualizados. Usa TLS, raíz pública `public/`, permisos mínimos y límites en el servidor/proveedor para tráfico volumétrico. El WAF PHP no aporta mitigación de DDoS antes de llegar al servidor. Consulta [la documentación oficial de CRS](https://coreruleset.org/docs/) para instalación y ajuste de reglas.

La retención de eventos, vencimiento de bloqueos y respaldos programados necesita ejecutar el trabajador. Ejemplo de cron, como usuario de la aplicación, adaptando el ejecutable y la ruta:

```cron
*/15 * * * * /usr/bin/php /var/www/goldensite/tools/maintenance.php >> /var/log/goldensite-maintenance.log 2>&1
```

El trabajador evita ejecuciones simultáneas, genera SQL diario o semanal si corresponde e informa su última ejecución en configuración. El administrador debe proteger y rotar sus registros y respaldos; no se eliminan automáticamente las copias SQL antiguas. No se ha configurado cron en un VPS todavía.

## Validación reproducible

`tests/run.php` verifica seguridad base mediante HTTP y MariaDB. `tests/install.php` instala un ZIP en una base vacía dedicada, comprueba Owner y módulos e incorpora `tests/console.php`: los 21 estilos, separación de perfiles, restauración, configuración inválida, secreto SMTP cifrado, reglas WAF, modo observación, CIDR, revocación efectiva, reparación de sesiones y trabajador de respaldos. Requiere las variables de prueba documentadas por los propios ejecutables; nunca uses la base de producción como destino.

Las pruebas visuales locales cubren dashboard, configuración, WAF, catálogo y personalizador a 1440 y 420 píxeles, además de la apertura y cierre del menú móvil. Las capturas privadas se guardan bajo `storage/console-review`. Estas verificaciones no equivalen a una auditoría externa ni garantizan ausencia de fallos futuros.
