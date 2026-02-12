# Refresh tokens

Los refresh tokens se almacenan en base de datos y se rotan al usarse.

Archivos clave:
- `app/Services/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`

Variables de entorno:
- `JWT_REFRESH_TOKEN_TTL`

Notas:
- Los tokens viven en la tabla `refresh_tokens`.
- El refresh usa transaccion y bloqueo para evitar carreras.
- Los tokens revocados se marcan con `revoked_at`.
