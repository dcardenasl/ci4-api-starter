# Exception System

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 10.

## Exception Hierarchy

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

## When to Use Each Exception

| Exception | Status | Use When |
|-----------|--------|----------|
| `NotFoundException` | 404 | Resource not found in database |
| `AuthenticationException` | 401 | Invalid credentials or token |
| `AuthorizationException` | 403 | User lacks required permissions |
| `ValidationException` | 422 | Data validation failed |
| `BadRequestException` | 400 | Malformed request, missing params |
| `ConflictException` | 409 | Duplicate entry, state conflict |

## Usage

```php
// Not found
throw new NotFoundException('User not found');

// Validation (with errors array)
throw new ValidationException('Validation failed', [
    'email' => 'Email is required',
    'price' => 'Price must be positive',
]);

// Unauthorized
throw new AuthenticationException('Invalid credentials');

// Forbidden
throw new AuthorizationException('Admin access required');
```

## Exception Handling

All exceptions are caught in `ApiController::handleException()` and automatically converted to appropriate HTTP responses with consistent JSON structure.

**See `../ARCHITECTURE.md` section 10 for complete exception implementation.**
