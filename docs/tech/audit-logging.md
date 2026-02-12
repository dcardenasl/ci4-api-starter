# Audit Logging

Audit logs capture create, update, and delete actions on entities.

Key files:
- `app/Services/AuditService.php`
- `app/Models/AuditLogModel.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

Notes:
- Records are stored in the `audit_logs` table.
- API endpoints are defined under `app/Controllers/Api/V1/AuditController.php`.
