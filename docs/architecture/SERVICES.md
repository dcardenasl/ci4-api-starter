# Service Layer & IoC

The Service layer contains all business logic and orchestrates domain operations. In this architecture, services are **"Pure"** and **Decoupled**.

---

## Pure Service Pattern

Services should NOT have any knowledge of HTTP, JSON, or `ApiResponse`.

- **Input:** Specific DTOs or scalar types.
- **Output:** DTOs, Entities, or pure arrays.
- **Errors:** Thrown as custom Exceptions (e.g., `AuthenticationException`).

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
