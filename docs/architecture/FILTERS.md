# Filters (Middleware)


## Filter Pipeline

Every request passes through filters in this order:

```
CorsFilter → ThrottleFilter → JwtAuthFilter → RoleAuthFilter → Controller
```

## Available Filters

| Filter | Alias | Purpose | Configuration |
|--------|-------|---------|---------------|
| `CorsFilter` | `cors` | Handle CORS and preflight | `.env` CORS_* |
| `ThrottleFilter` | `throttle` | Rate limiting (60 req/min) | `RATE_LIMIT_*` |
| `JwtAuthFilter` | `jwtauth` | Validate JWT token | `JWT_SECRET_KEY` |
| `RoleAuthorizationFilter` | `roleauth` | Check user role | Route arg: `roleauth:admin` |

## Usage in Routes

```php
// app/Config/Routes.php

// Public with throttle
$routes->group('', ['filter' => 'throttle'], function ($routes) {
    $routes->post('auth/login', 'AuthController::login');
});

// Protected (JWT required)
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('users', 'UserController::index');
    
    // Admin only
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('users', 'UserController::create');
    });
});
```

## Creating Custom Filters

```php
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Logic before controller
        // Return response to stop pipeline, or null to continue
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Logic after controller
        // Return modified response
    }
}

// Register in app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];
```

