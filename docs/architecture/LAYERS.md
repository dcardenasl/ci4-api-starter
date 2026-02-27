# The Architecture Layers

This document explains each layer in detail: Controller, DTO, Service, Model, and Entity.

---

## Controller Layer

**Location:** `app/Controllers/Api/V1/`

**Responsibility:** Act as a thin orchestrator between the HTTP Request and the Service Layer.

### ApiController (Base Class)

All API controllers extend `ApiController.php`. It provides a declarative `handleRequest` method that automates:
1. Data collection from all sources (GET, POST, JSON, Files).
2. Request DTO instantiation and self-validation (when DTO class is provided).
3. Exception handling and transformation to JSON.
4. Response normalization (success/error wrapping and `data` keying).

### Pattern: Declarative Orchestration

```php
public function create(): ResponseInterface
{
    // The controller declares WHAT to execute and WHICH DTO validates input.
    return $this->handleRequest('store', UserStoreRequestDTO::class);
}
```

---

## DTO Layer (Data Transfer Objects)

**Location:** `app/DTO/`

**Responsibility:** Data Integrity, Type Safety, and Contract Stability.

### Request DTOs (Input Guardians)
- **Base Class:** Extend `BaseRequestDTO`.
- **Auto-validation:** Validation happens in the constructor via the `rules()` method. 
- **Safety:** If a DTO object exists in memory, the data is guaranteed to be valid.
- **Inmutability:** PHP 8.2 `readonly` classes.

### Response DTOs (Output Contracts)
- **Explicit Contract:** Define exactly what the client receives.
- **Mapeo:** Use `fromArray()` to normalize data from entities.
- **Documentation:** Contain OpenAPI `#[OA\Property]` attributes.

---

## Service Layer

**Location:** `app/Services/`

**Responsibility:** Business logic, transactional integrity, and domain rules.

### Generic & Pure Services

Services should extend `BaseCrudService` for standard operations.

```php
class UserService extends BaseCrudService implements UserServiceInterface
{
    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function() use ($request) {
            // 1. Business Logic (specific to users)
            // 2. Delegation to Model
            // 3. Return typed Response DTO
        });
    }
}
```

`BaseCrudService` contract:
- `index()` returns `PaginatedResponseDTO` (`DataTransferObjectInterface`).
- `show()/store()/update()` return resource Response DTOs.
- `destroy()` returns `bool` and is normalized by `ApiController`.

### Mandates
- ✅ **Atomic:** Use `HandlesTransactions` trait for state changes.
- ✅ **Typed:** Always accept request DTOs and return DTOs for reads.
- ✅ **Command Outcomes:** Use `OperationResult` for command-style outcomes (accepted, revoke, etc.).
- ✅ **HTTP Agnostic:** No knowledge of JSON, status codes, or sessions.

---

## Model & Entity Layers

**Location:** `app/Models/` & `app/Entities/`

**Responsibility:** Data persistence and representation.

- **Models:** Use CodeIgniter 4 Query Builder.
- **Auditable:** Models use the `Auditable` trait for automatic trail logging.
- **Entities:** Represent row data. Must be converted to **Response DTOs** in the service layer.

---

## Summary

| Layer | Responsibility | Pattern |
|-------|----------------|---------|
| **Controller** | Orchestration | Declarative via `handleRequest` |
| **DTO** | Integrity | Self-validating constructor |
| **Service** | Logic | Pure & Transactional |
| **Model** | Persistence | Auditable Query Builder |
| **Entity** | Representation | Strongly Typed Row |

**Flow:**
```
Request → Controller (Orchestrator) → RequestDTO (Guardian) → Service (Logic) → Model → Entity → ResponseDTO (Contract) → JSON
```
