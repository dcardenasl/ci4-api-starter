# Registro de auditoría

Los registros de auditoría capturan acciones de creacion, actualizacion y eliminacion sobre entidades, mas eventos de control de seguridad.

Archivos clave:
- `app/Services/System/AuditService.php`
- `app/Services/System/AuditWriter.php`
- `app/Models/AuditLogModel.php`
- `app/Libraries/Queue/Jobs/WriteAuditLogJob.php`
- `app/Config/Audit.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

## Comportamiento en runtime (modo hibrido)
- Los eventos criticos de auditoria se persisten de forma sincronica (best effort).
- Los eventos no criticos se encolan de forma asincrona en la cola `audit`.
- Si falla el enqueue, `AuditService` hace fallback a persistencia sincronica.
- Los payloads se limitan por tamano antes de encolar para proteger `jobs.payload`.

Criticos por defecto:
- Cualquier evento con `severity=critical`.
- Acciones en `Config\\Audit::$criticalActions`:
  - `authorization_denied_role`
  - `api_key_auth_failed`
  - `api_key_rate_limit_exceeded`
  - `revoked_token_reuse_detected`

## Configuracion
- `AUDIT_ASYNC_ENABLED` (default: `true`, en testing default `false`)
- `AUDIT_QUEUE_NAME` (default: `audit`)
- `AUDIT_MAX_PAYLOAD_BYTES` (default: `60000`)

## Operacion
- Ejecutar workers incluyendo la cola de auditoria, por ejemplo:
  - `php spark queue:work --queue=audit`
- Monitorear backlog y fallos:
  - `jobs` filtrado por `queue='audit'`
  - `failed_jobs` filtrado por `queue='audit'`

Notas:
- Los registros se guardan en `audit_logs`.
- Los endpoints están en `app/Controllers/Api/V1/Admin/AuditController.php`.
- La validación de entrada para acciones de auditoría (`index`, `show`, `by_entity`) está centralizada y la consume `AuditService`.
