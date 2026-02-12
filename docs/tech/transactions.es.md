# Transacciones

Las transacciones de base de datos se usan para mantener consistencia en flujos criticos.

Archivos clave:
- `app/Services/RefreshTokenService.php`

Notas:
- La rotacion de refresh tokens corre dentro de una transaccion.
- El refresh token se bloquea con `FOR UPDATE` para evitar uso concurrente.
