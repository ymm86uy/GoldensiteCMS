# Goldensite: módulos y política comercial

Revisión del 4 de noviembre de 2026. Complementa los informes anteriores. El alcance de los 33 módulos está disponible en Salud del sitio y en «Alcance y requisitos» de cada tarjeta; no se presenta una activación como prueba de funcionamiento de un proveedor.

## Identidad y catálogo

Los 33 nombres oficiales comienzan con «Goldensite ». Se actualizaron los manifiestos físicos y la cabecera administrativa. Los identificadores internos, alias, datos y códigos de licencia se conservan para no romper instalaciones existentes. Los precios del Owner son la fuente comercial; los manifiestos ya no guardan importes contradictorios.

En Owner, después del desbloqueo exclusivo, cada ficha permite guardar:

- Categoría FREE o PRO.
- Precio base con dos decimales, moneda y pago único, mensual o anual.
- Porcentaje de descuento de 0 a 100 e inicio/fin opcionales en la zona horaria del sitio.

FREE permite activar sin licencia; PRO exige una licencia válida y la comprobación se aplica también a las rutas administrativas. Un cambio de categoría no activa el módulo automáticamente ni modifica sus datos. Las licencias conservadas vuelven a verificarse si el módulo pasa de nuevo a PRO. La seguridad básica del núcleo no depende de comprar un módulo.

El catálogo muestra la categoría efectiva, el precio base tachado y el precio descontado mientras la promoción está vigente. El cálculo usa céntimos y redondeo entero. Un descuento del 100 % no convierte PRO en FREE. Las condiciones se guardan en los pedidos nuevos; los pagos y acuerdos históricos conservan su importe y precio registrado.

Una promoción vigente del 100 % permite registrar y autorizar una licencia promocional de importe 0.00, separada de los pagos reales. Requiere marcar expresamente esa modalidad en Owner y usar la moneda de la oferta. Se conserva la autorización y la referencia única; no se inventa un ingreso bancario para emitir una clave gratuita.

Las categorías y promociones de una instalación cliente se actualizan con el catálogo firmado que exporta el Owner. La importación exige la firma de Goldensite y una revisión posterior a la aplicada. No se entrega la clave privada. Los nuevos paquetes incluyen la política pública firmada vigente. Todavía no existe sincronización automática por dominio o servidor central; no requiere inventar una sede que aún no se ha contratado.

## Goldensite Newsletter

Se amplió el módulo existente: consentimiento explícito, confirmación de dirección, bajas firmadas, borradores editables, revisión, programación y campañas para un máximo de 2000 destinatarios confirmados por campaña.

La solicitud de alta queda pendiente. El trabajador envía un enlace válido durante 48 horas; abrirlo no confirma el alta hasta que la persona pulse el botón. Los suscriptores históricos sin confirmación no se incluyen automáticamente en campañas. El consentimiento se renueva al volver a suscribirse; los enlaces antiguos no sirven para una nueva versión de la suscripción.

Cada destinatario se comprueba de nuevo antes de enviar. La baja sigue disponible aunque se desactive el módulo. Los enlaces GET no realizan la baja, para evitar que un escáner de correo cancele la suscripción. Las campañas se guardan primero como borrador y programarlas exige la contraseña actual. No se interpreta la aceptación SMTP como entrega garantizada en la bandeja de entrada.

Se utiliza el transporte SMTP del sitio, sin duplicar su configuración. Hay que verificar remitente, autenticación y condiciones del proveedor antes de utilizarlo en producción. Los envíos deben respetar el consentimiento y los límites de su proveedor.

## Goldensite Automation Hub

Se añadieron ejecución en cola y demoras reales entre 0 y 604800 segundos. Los eventos conectados incluyen artículos y páginas publicados, cuentas registradas, consultas y formularios recibidos. El evento de formulario transmite identificadores, no respuestas personales. Las condiciones comparan valores escalares; las comparaciones numéricas exigen números reales.

El registro local inmediato permanece disponible. Correo, webhooks y registros demorados se procesan fuera de la visita del usuario. La validación local sigue siendo explícitamente una validación y no afirma haber ejecutado una acción externa. Una regla desactivada, eliminada o modificada no ejecuta tareas con una configuración anterior.

## Cola compartida y operación

Boletín y automatizaciones utilizan una cola privada común con contenido cifrado, deduplicación, programación, reclamación exclusiva y hasta cinco intentos. No se creó otro módulo que repitiera sus funciones. Los webhooks conservan su cola específica de protocolos y confirmaciones.

En el VPS se deben programar, con el usuario de la aplicación y rutas reales:

```cron
* * * * * /usr/bin/php /var/www/goldensite/tools/module-worker.php
* * * * * /usr/bin/php /var/www/goldensite/tools/webhook-worker.php
```

No se inició un trabajador de envíos en la instalación del propietario durante la revisión. Las pruebas utilizan receptores simulados desde CLI y no envían mensajes a proveedores reales.

Un proceso interrumpido durante una operación externa puede haber alcanzado al proveedor. Esa tarea se marca «uncertain» para revisión y no se reenvía automáticamente. Un fallo de transporte que se reintenta puede producir duplicados si el proveedor aceptó antes de perderse la conexión: conviene deduplicar por identificador cuando el receptor lo permita. Cancelar una campaña evita envíos pendientes; no retira mensajes ya aceptados o en curso.

Salud del sitio permite cancelar tareas y preparar otro presupuesto de intentos para tareas fallidas. Requiere la contraseña actual y registra la acción en auditoría. Si el resultado es incierto, exige confirmar que se revisó el riesgo de duplicar una entrega. No cambia tareas que ya están ejecutándose o terminadas.

## Salud del sitio y límites

Goldensite Site Health & Doctor reúne los requisitos locales, estado de las colas, alcance y dependencias de los 33 módulos. Permite descargar un informe que excluye credenciales, direcciones de destinatarios y cuerpos privados.

Se conserva el bloqueo de SAML + SCIM no implementados. OIDC corresponde a Goldensite Identity & Access. Las pasarelas reales de Marketplace, Commerce y Dating siguen pendientes de integración; no se simulan cobros. Deploy Manager administra versiones de contenido y no servidores. Universal Importer no garantiza reproducir extensiones, temas o medios de un CMS ajeno. La biblioteca DAM produce WebP y no anuncia AVIF o indexación EXIF inexistentes.

Para actualizar una instalación existente: realizar respaldo y ejecutar `php tools/migrate-modules.php`. La migración es aditiva y no confirma automáticamente altas antiguas, cambia categorías ni ejecuta trabajos.

## Pruebas incluidas

La batería instala el ZIP en una base exclusiva y comprueba las rutas de los 33 módulos, nombres, cambios FREE/PRO, requisitos de licencia, porcentajes, vigencias y firma del catálogo. También comprueba doble confirmación, bajas, destinatarios excluidos, programación idempotente, borradores multilínea, entregas simuladas, demoras y reintentos, tareas interrumpidas, evento real de formulario y exportación sin datos privados. Las integraciones con proveedores y una carga representativa de producción requieren pruebas propias en el entorno final.
