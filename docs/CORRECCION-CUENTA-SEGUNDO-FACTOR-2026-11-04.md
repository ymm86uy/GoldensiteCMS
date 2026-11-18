# Cuenta y segundo factor: separación de módulos

4 de noviembre de 2026.

La ruta del núcleo `/mi-cuenta` utilizaba una plantilla antigua acoplada a perfiles Dating. Consultaba sus datos y ofrecía membresías, descargas y enlaces al módulo sin comprobar su activación. Además, la pantalla de segundo factor siempre regresaba allí, incluso si se había accedido desde el dashboard administrativo.

## Corrección

- El segundo factor conserva autenticación, contraseña actual, CSRF, límites, TOTP, cifrado del secreto y códigos de recuperación de un solo uso. La cuenta administrativa usa la consola con el tema activo y vuelve al dashboard, respetando la ruta administrativa configurada.
- El límite de operaciones se comprueba antes de validar la contraseña actual; los intentos incorrectos también consumen el presupuesto de cinco intentos en cinco minutos.
- El usuario sin acceso administrativo conserva la pantalla pública de seguridad y vuelve a su cuenta del núcleo.
- `/mi-cuenta` muestra identidad y estado real del segundo factor. No consulta perfiles Dating, no calcula un VIP ni presenta suscripciones, compras vacías o afirmaciones de cifrado WAF como si fueran datos reales.
- El acceso a Dating solo aparece si el módulo está activo y su licencia es válida. Live Chat tiene su comprobación independiente, porque es un módulo gratuito distinto.
- La cuenta no concede permisos administrativos. Los servicios conservan sus comprobaciones de permisos y activación en cada petición.
- El chat tiene controles adaptados al tema, estado vacío explícito y navegación de salas. El acceso flotante deja de repetirse dentro del propio chat. Desactivarlo bloquea tanto su pantalla como la API; la pantalla de bloqueo también usa el tema.
- No se activó ningún módulo ni se modificó el segundo factor, la contraseña o la membresía de cuentas reales para probar la corrección.

## Comprobación

La instalación de prueba aislada superó 552 comprobaciones, incluyendo el ciclo de alta TOTP y recuperación existente y nuevas regresiones: regreso administrativo, regreso de usuario ordinario, Dating desactivado, chat independiente, chat desactivado, licencia activa, límite de intentos incorrectos y rechazo de acceso administrativo para un usuario ordinario.

En la instalación local se comprobó el clic real de regreso a `/admin/dashboard`, la ausencia de enlaces Dating con el módulo desactivado, el tema activo y la adaptación de las pantallas de cuenta. Las verificaciones no habilitaron el segundo factor de la cuenta del propietario.
