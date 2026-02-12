# Filters (Middleware)


## Pipeline de Filtros

Cada petición pasa a través de filtros en este orden:

```
CorsFilter → ThrottleFilter → JwtAuthFilter → RoleAuthFilter → Controller
```

## Filtros Disponibles

| Filter | Alias | Propósito | Configuración |
|--------|-------|---------|---------------|
| `CorsFilter` | `cors` | Manejar CORS y preflight | `.env` CORS_* |
| `ThrottleFilter` | `throttle` | Rate limiting (60 req/min) | `RATE_LIMIT_*` |
| `JwtAuthFilter` | `jwtauth` | Validar token JWT | `JWT_SECRET_KEY` |
| `RoleAuthorizationFilter` | `roleauth` | Verificar rol de usuario | Arg de ruta: `roleauth:admin` |

## Uso en Rutas

```php
// app/Config/Routes.php

// Público con throttle
$routes->group('', ['filter' => 'throttle'], function ($routes) {
    $routes->post('auth/login', 'AuthController::login');
});

// Protegido (JWT requerido)
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('users', 'UserController::index');

    // Solo admin
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('users', 'UserController::create');
    });
});
```

## Crear Filtros Personalizados

```php
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Lógica antes del controller
        // Retornar respuesta para detener pipeline, o null para continuar
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Lógica después del controller
        // Retornar respuesta modificada
    }
}

// Registrar en app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];
```

