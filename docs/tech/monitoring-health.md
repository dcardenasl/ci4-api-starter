# Monitoring and Health

Health endpoints expose basic readiness and liveness checks.

Key files:
- `app/Controllers/Api/V1/HealthController.php`
- `app/Libraries/Monitoring/HealthChecker.php`
- `app/Config/Routes.php`

Endpoints:
- `GET /health` (full check summary)
- `GET /ping` (simple ok)
- `GET /ready` (database readiness)
- `GET /live` (liveness)

Notes:
- `checkAll()` currently includes database, disk space, and writable folders.
- Additional checks exist for queue, email, and Redis, but are not part of `checkAll()` by default.
- These endpoints are operational/monitoring endpoints and intentionally use their own payload shape (not `ApiResponse`).
