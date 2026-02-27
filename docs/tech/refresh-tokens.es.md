# Tokens de refresco

Los tokens de refresco se almacenan en base de datos y se rotan al usarse.

Archivos clave:
- `app/Services/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Validations/TokenValidation.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`

Variables de entorno:
- `JWT_REFRESH_TOKEN_TTL`

Validación:
- Las acciones `token:refresh` y `token:revoke` requieren `refresh_token` con la regla `valid_token[64]`.
- Un formato inválido del token se trata como error de validación de la solicitud.

Notas:
- Los tokens viven en la tabla `refresh_tokens`.
- El refresh usa transacción y bloqueo para evitar carreras.
- Los tokens revocados se marcan con `revoked_at`.
