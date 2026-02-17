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

SLO-oriented API request indicators are available in metrics endpoints:
- `GET /api/v1/metrics`
- `GET /api/v1/metrics/requests`

Included indicators:
- `p95_response_time_ms`
- `p99_response_time_ms`
- `error_rate_percent`
- `availability_percent`
- `status_code_breakdown` (`2xx`, `3xx`, `4xx`, `5xx`)

Configuration:
- `SLO_API_P95_TARGET_MS` (default: `500`)
