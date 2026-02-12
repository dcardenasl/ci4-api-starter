# JWT Authentication

JWT access tokens are issued on login and validated by a request filter.

Key files:
- `app/Services/JwtService.php`
- `app/Filters/JwtAuthFilter.php`
- `app/Controllers/Api/V1/AuthController.php`
- `app/Config/Services.php`

Environment variables:
- `JWT_SECRET_KEY`
- `JWT_ACCESS_TOKEN_TTL`
- `JWT_REVOCATION_CHECK`

Notes:
- Tokens are expected in `Authorization: Bearer <token>` headers.
- The filter injects `userId` and `userRole` into the request.
