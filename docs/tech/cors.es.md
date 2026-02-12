# CORS

CORS se maneja con un filtro que procesa preflight y agrega headers.

Archivos clave:
- `app/Filters/CorsFilter.php`
- `app/Config/Cors.php`
- `app/Config/Filters.php`

Variables de entorno:
- `CORS_ALLOWED_ORIGINS`

Notas:
- Los origenes se validan contra `CORS_ALLOWED_ORIGINS`.
- En produccion, si no hay origenes configurados, usa el URL de la app.
