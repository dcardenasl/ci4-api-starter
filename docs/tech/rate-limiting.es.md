# Rate limiting

El rate limiting se aplica con filtros HTTP para rutas generales y de auth.

Archivos clave:
- `app/Filters/ThrottleFilter.php`
- `app/Filters/AuthThrottleFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`

Variables de entorno:
- `RATE_LIMIT_REQUESTS`
- `RATE_LIMIT_WINDOW`
- `AUTH_RATE_LIMIT_REQUESTS`
- `AUTH_RATE_LIMIT_WINDOW`

Notas:
- `authThrottle` se aplica a endpoints de auth.
- `throttle` se aplica a rutas generales de la API.
