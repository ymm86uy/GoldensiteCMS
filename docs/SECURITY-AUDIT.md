# Goldensite Security Audit

La auditoría se registra desde `app/Core/ModuleCatalog.php` y usa las rutas administrativas de `routes/modules.php`.

- `modules/GoldensiteSecurityAudit/SecurityScanner.php` coordina las comprobaciones de solo lectura.
- `app/Controllers/Admin/SecurityAuditController.php` controla acceso, activación, CSRF y frecuencia de ejecución.
- `views/admin/security_audit/report.php` presenta gravedad, ubicación, motivo y corrección.
- El escáner reutiliza la conexión PDO del CMS, las comprobaciones del instalador, el WAF, el analizador heurístico, el cliente HTTPS y el verificador de huellas del núcleo.

En Owner, actívalo desde **Módulos y extensiones**. En Comercial requiere una licencia firmada válida para el dominio. Owner puede fijar el precio y emitir licencias desde el mecanismo comercial existente.

En **Infraestructura → Auditoría de seguridad**, un superadministrador puede ejecutar la revisión. El informe no conserva contenidos de archivos, contraseñas, secretos TOTP ni valores de cookies. El registro de actividad guarda el evento y el número de hallazgos. Las correcciones se presentan como instrucciones para que el administrador decida cuándo aplicarlas.

La auditoría encuentra archivos sensibles dentro del directorio público y señales de configuración insegura. Una presencia en disco no confirma exposición por HTTP. La consulta de cabeceras usa solo la URL pública HTTPS configurada en `APP_URL`. No sigue redirecciones y no lee respaldos, configuración ni otros archivos sensibles por HTTP. Las heurísticas de código pueden requerir revisión manual por un desarrollador. Para verificar integridad, coloca el manifiesto que corresponde exactamente a la edición y versión en `storage/audit-release-manifest.json`. Genera el manifiesto desde un instalador oficial comprobado con `tools/release-integrity.php manifest`.
