# Integridad de la instalación

Desde 1.0.38, los instaladores oficiales incluyen `app/release-integrity.nfx`: una referencia de hashes SHA-256 firmada con la identidad de distribución de GoldenSiteCMS. Cada edición tiene su propia referencia. El parche incorpora la correspondiente a su versión de destino.

En **Visión general** y **Release Manager & Advanced Backup**, el superadministrador puede pulsar **Comprobar archivos oficiales**, confirmando su contraseña actual. Se reutilizan la autenticación, los permisos, CSRF, los límites de frecuencia, el registro de actividad y el comparador existentes. Las dos pantallas comparten la misma vista y el mismo informe. Security Audit también reutiliza esa referencia; mantiene compatibilidad con el manifiesto local anterior.

## Interpretación

- **Pendiente:** no hay un informe válido para la referencia instalada.
- **Sin diferencias en la última comprobación:** todos los archivos incluidos coincidían al realizar el análisis.
- **Diferencias detectadas:** hay archivos faltantes o con otro contenido. Se muestran hasta 30 rutas por grupo al superadministrador y el número total de diferencias.
- **Parcial:** no se pudieron comprobar todos los archivos dentro del presupuesto de lectura o tiempo. Nunca equivale a una comprobación completa.
- **Pendiente de renovar:** el informe tiene más de 24 horas. Se conserva su fecha y sus resultados históricos.
- **Referencia no disponible:** falta, su firma no es válida o no corresponde a la edición y versión instaladas. No se crea una referencia nueva desde archivos potencialmente alterados.

El informe se guarda en los ajustes existentes, autenticado con una clave de la instalación y vinculado a la referencia exacta. Una actualización de referencia, edición o versión invalida el resultado anterior. Una modificación posterior al análisis requiere otra comprobación para ser detectada; el panel no ejecuta un escaneo continuo.

## Alcance y límites

Se comparan el código y los recursos oficiales admitidos por el actualizador. Se excluyen datos, subidas, configuración privada, claves y el propio archivo de referencia para evitar una dependencia circular. Las extensiones añadidas por el administrador no se consideran archivos oficiales de esta compilación y requieren la auditoría y el antivirus existentes.

El análisis no modifica ni borra archivos, no repara automáticamente y no demuestra ausencia de vulnerabilidades. Los cambios autorizados también producen diferencias. Antes de restaurar, compara con el instalador oficial de la misma edición y versión y conserva una copia para análisis.

Se aplica un límite de ocho segundos y 128 MiB de lectura al análisis manual. Security Audit conserva su presupuesto de 40 MiB. Los resultados incompletos se declaran parciales. Se permite una ejecución por minuto por instalación y tres por cuenta cada quince minutos, incluyendo intentos fallidos de autenticación.

La firma protege la referencia frente a cambios sin la clave del emisor. Como cualquier comprobador dentro del sitio, depende de que el propio código de verificación y la clave pública no estén comprometidos. Ante una sospecha de compromiso del servidor, realiza también una comparación externa con el paquete oficial.
