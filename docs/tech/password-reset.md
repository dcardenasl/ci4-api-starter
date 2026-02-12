# Password Reset

Password reset uses a token stored in a separate table and sent by email.

Key files:
- `app/Services/PasswordResetService.php`
- `app/Controllers/Api/V1/PasswordResetController.php`
- `app/Models/PasswordResetModel.php`
- `app/Database/Migrations/2026-01-29-200145_CreatePasswordResetsTable.php`

Notes:
- Tokens are stored in `password_resets` with email and created timestamp.
- Endpoints: `POST /api/v1/auth/forgot-password`, `GET /api/v1/auth/validate-reset-token`, `POST /api/v1/auth/reset-password`.
- Emails are queued via `EmailService::queueTemplate()`.
