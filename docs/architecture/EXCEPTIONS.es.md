# Sistema de Excepciones


## Jerarquía de Excepciones

```
Exception
    └── ApiException (abstract)
            ├── NotFoundException         (404)
            ├── AuthenticationException   (401)
            ├── AuthorizationException    (403)
            ├── ValidationException       (422)
            ├── BadRequestException       (400)
            ├── ConflictException         (409)
            └── TooManyRequestsException  (429)
```

## Cuándo Usar Cada Excepción

| Exception | Estado | Usar Cuando |
|-----------|--------|----------|
| `NotFoundException` | 404 | Recurso no encontrado en base de datos |
| `AuthenticationException` | 401 | Credenciales o token inválidos |
| `AuthorizationException` | 403 | Usuario carece de permisos requeridos |
| `ValidationException` | 422 | Validación de datos falló |
| `BadRequestException` | 400 | Petición malformada, parámetros faltantes |
| `ConflictException` | 409 | Entrada duplicada, conflicto de estado |

## Uso

```php
// No encontrado
throw new NotFoundException('User not found');

// Validación (con array de errores)
throw new ValidationException('Validation failed', [
    'email' => 'Email is required',
    'price' => 'Price must be positive',
]);

// No autorizado
throw new AuthenticationException('Invalid credentials');

// Prohibido
throw new AuthorizationException('Admin access required');
```

## Manejo de Excepciones

Todas las excepciones son capturadas en `ApiController::handleException()` y automáticamente convertidas a respuestas HTTP apropiadas con estructura JSON consistente.

**Ver `../ARCHITECTURE.md` sección 10 para implementación completa de excepciones.**
