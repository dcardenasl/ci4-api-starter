# Rate limiting

El rate limiting se aplica con filtros HTTP para rutas generales y de auth.

Archivos clave:
- `app/Filters/ThrottleFilter.php`
- `app/Filters/AuthThrottleFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`

Variables de entorno:
- `RATE_LIMIT_REQUESTS`
- `RATE_LIMIT_USER_REQUESTS`
- `RATE_LIMIT_WINDOW`
- `AUTH_RATE_LIMIT_REQUESTS`
- `AUTH_RATE_LIMIT_WINDOW`
- `API_KEY_RATE_LIMIT_DEFAULT`
- `API_KEY_USER_RATE_LIMIT_DEFAULT`
- `API_KEY_IP_RATE_LIMIT_DEFAULT`
- `API_KEY_WINDOW_DEFAULT`

Notas:
- `authThrottle` se aplica a endpoints de auth.
- `throttle` se aplica a rutas generales de la API.
- Las respuestas incluyen headers de rate limit (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`).
- Si llega `X-App-Key`, se aplican límites de API key tras validar la clave.
