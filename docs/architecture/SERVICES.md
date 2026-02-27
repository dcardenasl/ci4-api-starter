# Service Layer & IoC

The Service layer contains all business logic and orchestrates domain operations. In this architecture, services are **organized into domains** and follow a **composition pattern**.

---

## Domain-Driven Organization

Services are grouped by functional domain to ensure high cohesion and prevent "God Classes".

- `app/Services/Auth/`: Login, Registration, OAuth.
- `app/Services/Tokens/`: JWT, Refresh Tokens, Revocation.
- `app/Services/Users/`: Identity and RBAC.
- `app/Services/Files/`: Storage and File Processing.
- `app/Services/System/`: Infrastructure (Audit, Email, Metrics).

---

## Service Composition Pattern

Large services are decomposed into specialized components injected via constructor. This keeps orchestrators thin and logic testable.

### Support Components:
- **Handlers**: Encapsulate multi-step logic (e.g., `GoogleAuthHandler`).
- **Guards**: Centralize security assertions (e.g., `UserRoleGuard`).
- **Mappers**: Handle entity-to-DTO transformations (e.g., `AuthUserMapper`).

---

## Pure Service Pattern

Services are **Pure** and **Stateless**. They should NOT have knowledge of HTTP or JSON.

- **Input:** Specific DTOs or Scalar types.
- **Output:** DTOs, Entities, or `OperationResult`.
- **Errors:** Thrown as custom Exceptions implementing `HasStatusCode`.

### Example (Composition)

```php
// app/Services/Auth/AuthService.php
public function login(LoginRequestDTO $request): LoginResponseDTO
{
    $user = $this->userModel->where('email', $request->email)->first();
    
    // Delegate security to Guard
    $this->userAccessPolicy->assertCanAuthenticate($user);
    
    // Delegate session creation to Manager
    $session = $this->sessionManager->generateSessionResponse(
        $this->userMapper->mapAuthenticated($user)
    );
    
    return LoginResponseDTO::fromArray($session);
}
```

---

## Registering Services (IoC)

All services and their dependencies must be registered in `app/Config/Services.php`.

```php
// app/Config/Services.php
public static function authService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('authService');
    }
    
    return new \App\Services\Auth\AuthService(
        static::userModel(),
        static::jwtService(),
        static::refreshTokenService(),
        new \App\Services\Auth\Support\AuthUserMapper(),
        new \App\Services\Auth\Support\SessionManager(
            static::jwtService(),
            static::refreshTokenService()
        )
    );
}
```

---

## Benefits

1. **Testability:** Mock support components to test orchestrators in isolation.
2. **Immutability:** Most services are `readonly class` (PHP 8.2+), preventing side effects.
3. **Discoverability:** Clear domain folders make it easy to find relevant logic.
