# Rate Limiting

Rate limiting is enforced through HTTP filters for general and auth-specific routes.

Key files:
- `app/Filters/ThrottleFilter.php`
- `app/Filters/AuthThrottleFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`

Environment variables:
- `RATE_LIMIT_REQUESTS`
- `RATE_LIMIT_WINDOW`
- `AUTH_RATE_LIMIT_REQUESTS`
- `AUTH_RATE_LIMIT_WINDOW`

Notes:
- `authThrottle` is applied to auth endpoints.
- `throttle` is applied to general API routes.
