# Refresh Tokens

Refresh tokens are stored in the database and rotated on use.

Key files:
- `app/Services/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`

Environment variables:
- `JWT_REFRESH_TOKEN_TTL`

Notes:
- Tokens live in the `refresh_tokens` table.
- Refresh uses a DB transaction and row lock to avoid race conditions.
- Revoked tokens are marked with `revoked_at`.
