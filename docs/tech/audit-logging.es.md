# Audit logging

Los audit logs registran acciones de create, update y delete sobre entidades.

Archivos clave:
- `app/Services/AuditService.php`
- `app/Models/AuditLogModel.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

Notas:
- Los registros se guardan en `audit_logs`.
- Los endpoints estan en `app/Controllers/Api/V1/AuditController.php`.
- La validacion de entrada para acciones de auditoria (`index`, `show`, `by_entity`) esta centralizada y la consume `AuditService`.
