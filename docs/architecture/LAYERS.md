# The Architecture Layers

This document explains each layer in detail: Controller, DTO, Service, Model, and Entity.

---

## Controller Layer

**Location:** `app/Controllers/Api/V1/`

**Responsibility:** Handle HTTP requests, map data to DTOs, and delegate to services.

### ApiController (Base Class)

All API controllers extend `ApiController.php`:

```php
abstract class ApiController extends Controller
{
    protected function handleRequest(string|callable $target, ?array $params = null): ResponseInterface
    {
        try {
            if (is_callable($target)) {
                $result = $target();
            } else {
                $data = $this->collectRequestData($params);
                $result = $this->getService()->$target($data);
            }

            // Normalization happens here automatically
            return $this->respond($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
```

### Pattern: DTO-First

```php
public function login(): ResponseInterface
{
    // 1. Get validated DTO from request
    $dto = $this->getDTO(LoginRequestDTO::class);

    // 2. Delegate to service using a closure
    return $this->handleRequest(
        fn() => $this->getService()->login($dto)
    );
}
```

---

## DTO Layer (Data Transfer Objects)

**Location:** `app/DTO/`

**Responsibility:** Ensure data integrity, type safety, and contract stability.

### Request DTOs (Input)
- **Auto-validation:** They call `validateOrFail()` in their constructor.
- **Inmutability:** PHP 8.2 `readonly` classes.
- **Type Safety:** Property types are strictly enforced.

### Response DTOs (Output)
- **Sanitization:** They explicitly define what fields are exposed to the client.
- **Standardization:** They normalize data from Entities/Arrays (e.g., date formatting).
- **Documentation:** They contain OpenAPI `#[OA\Property]` attributes.

---

## Service Layer

**Location:** `app/Services/`

**Responsibility:** Business logic, orchestration, and domain operations.

### Pure Service Pattern

```php
class UserService implements UserServiceInterface
{
    public function store(array $data): UserResponseDTO
    {
        // 1. Logic
        $userId = $this->userModel->insert($data);
        $user = $this->userModel->find($userId);

        // 2. Return typed DTO (NO ApiResponse here!)
        return UserResponseDTO::fromArray($user->toArray());
    }
}
```

### Rules
- ✅ **Decoupled:** NO knowledge of `ApiResponse`, `status` codes, or JSON.
- ✅ **Typed:** Use DTOs for parameters and return values.
- ✅ **Exceptional:** Use custom Exceptions for all error states.
- ❌ NO direct request/response handling.

---

## Model & Entity Layers

**Location:** `app/Models/` & `app/Entities/`

**Responsibility:** Database operations and data representation.

- **Models:** Use CodeIgniter 4 Query Builder and `Auditable` traits.
- **Entities:** Represent a single row. They should be converted to **Response DTOs** before leaving the service layer to avoid accidental data leakage.

---

## Summary

| Layer | Responsibility | Input | Output |
|-------|----------------|-------|--------|
| **Controller** | HTTP I/O & Mapping | Request | JSON Response |
| **DTO** | Contract & Validation | Raw Array | Typed Object |
| **Service** | Business Logic | DTO/Object | DTO/Entity |
| **Model** | Database Ops | Array/Entity | Entity/Object |
| **Entity** | Row Representation | DB Row | Typed Properties |

**Flow:**
```
Request → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiResponse → JSON
```

Each layer is **independently testable** and has **one reason to change**.
