# Autenticación JWT

Los tokens de acceso JWT se generan durante el inicio de sesión y se validan con un filtro.

Archivos clave:
- `app/Services/JwtService.php`
- `app/Filters/JwtAuthFilter.php`
- `app/Controllers/Api/V1/AuthController.php`
- `app/Config/Services.php`

Variables de entorno:
- `JWT_SECRET_KEY`
- `JWT_ACCESS_TOKEN_TTL`
- `JWT_REVOCATION_CHECK`

Notas:
- Los tokens se envían en `Authorization: Bearer <token>`.
- El filtro inyecta `userId` y `userRole` en la petición.
