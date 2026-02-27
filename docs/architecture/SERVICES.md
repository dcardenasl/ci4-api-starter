# Service Layer & IoC

The Service layer contains all business logic and orchestrates domain operations. In this architecture, services are **"Pure"** and **Decoupled**.

---

## Pure Service Pattern

Services should NOT have any knowledge of HTTP, JSON, or `ApiResponse`.

- **Input:** Specific DTOs or scalar types.
- **Output:** DTOs, Entities, or `OperationResult` for command-like workflows.
- **Errors:** Thrown as custom Exceptions (e.g., `AuthenticationException`).

### Base CRUD Contract

For services implementing `CrudServiceContract` via `BaseCrudService`:

1. `index()` receives a Request DTO and returns `PaginatedResponseDTO`.
2. `show()`, `store()`, and `update()` return resource Response DTOs.
3. `destroy()` returns `bool` and is normalized by `ApiController`.

### Example

```php
// app/Services/UserService.php
public function store(UserCreateRequestDTO $request): UserResponseDTO
{
    $userId = $this->userModel->insert($request->toArray());
    
    // Return typed object
    return UserResponseDTO::fromArray($this->userModel->find($userId)->toArray());
}
```

## Command Outcomes (`OperationResult`)

For command-style operations that are not plain CRUD reads (e.g. token revoke, external auth decisions),
services should return `App\Support\OperationResult` instead of ad-hoc arrays.

Rules:

1. Use `OperationResult::success()` for regular command success.
2. Use `OperationResult::accepted()` for domain-accepted asynchronous/pending flows.
3. Use exceptions for failures (do not return HTTP-like error payloads from services).

---

## Registering Services (IoC)

Services are registered in the CodeIgniter 4 Service Container for dependency injection.

```php
// app/Config/Services.php
public static function userService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('userService');
    }
    
    return new \App\Services\UserService(
        new \App\Models\UserModel(),
        static::emailService(),
        new \App\Models\PasswordResetModel(),
        static::auditService()
    );
}
```

---

## Benefits of Decoupling

1. **Testability:** You can test services with simple object assertions, without mocking `ApiResponse`.
2. **Reusability:** The same service can be used by a Web Controller, a CLI Command, or a Cron Job.
3. **Clarity:** The interface clearly defines the data contract.
