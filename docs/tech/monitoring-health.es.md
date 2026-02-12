# Monitoreo y salud

Los endpoints de salud exponen checks de readiness y liveness.

Archivos clave:
- `app/Controllers/Api/V1/HealthController.php`
- `app/Libraries/Monitoring/HealthChecker.php`
- `app/Config/Routes.php`

Endpoints:
- `GET /health` (resumen completo)
- `GET /ping` (ok simple)
- `GET /ready` (readiness de base de datos)
- `GET /live` (liveness)

Notas:
- `checkAll()` incluye base de datos, espacio en disco y carpetas writable.
- Existen checks para cola, email y Redis, pero no se incluyen por defecto en `checkAll()`.
