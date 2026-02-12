# Restablecimiento de contrasena

El reset de contrasena usa un token guardado en una tabla separada y enviado por email.

Archivos clave:
- `app/Services/PasswordResetService.php`
- `app/Controllers/Api/V1/PasswordResetController.php`
- `app/Models/PasswordResetModel.php`
- `app/Database/Migrations/2026-01-29-200145_CreatePasswordResetsTable.php`

Notas:
- Los tokens se guardan en `password_resets` con email y timestamp.
- Endpoints: `POST /api/v1/auth/forgot-password`, `GET /api/v1/auth/validate-reset-token`, `POST /api/v1/auth/reset-password`.
- Los emails se encolan via `EmailService::queueTemplate()`.
