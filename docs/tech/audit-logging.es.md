# Registro de auditoría

Los registros de auditoría capturan acciones de creación, actualización y eliminación sobre entidades.

Archivos clave:
- `app/Services/AuditService.php`
- `app/Models/AuditLogModel.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

Notas:
- Los registros se guardan en `audit_logs`.
- Los endpoints están en `app/Controllers/Api/V1/AuditController.php`.
- La validación de entrada para acciones de auditoría (`index`, `show`, `by_entity`) está centralizada y la consume `AuditService`.
