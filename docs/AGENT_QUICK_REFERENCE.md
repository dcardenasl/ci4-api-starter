# Agent Quick Reference - CI4 API Starter (Domain-Driven Architecture)

**Purpose**: Essential patterns and constraints for AI agents to maintain architectural consistency.

---

## 1. Domain-Driven Organization

Every new component **must** reside in a domain subdirectory:
- `app/Services/{Domain}/`
- `app/Interfaces/{Domain}/`
- `app/DTO/Request/{Domain}/`
- `app/DTO/Response/{Domain}/`

---

## 2. Request Flow (Immutable & Decomposed)

```
HTTP Request → Controller → [RequestDTO] → Domain Service (Guards/Handlers) → Model → Entity → [ResponseDTO] → ApiResult → JSON
```

### Key Innovations:
- **`BaseRequestDTO`**: Automatically enriches `user_id` and `role` from `ContextHolder`.
- **`ApiResult`**: Standardization of `body` and `status` between layers.
- **`ExceptionFormatter`**: Centralized, environment-aware error handling.

---

## 3. Implementation Checklist

### Step 0: Scaffold First
```bash
php spark make:crud {Name} --domain {Domain} --route {endpoint}
```

### Step 1: Immutable DTOs
- Extend `BaseRequestDTO`.
- Use **`readonly class`** for all DTOs and Services.
- Response DTOs must include OpenAPI `#[OA\Property]` attributes.

### Step 2: Composed Services
- Inherit from `BaseCrudService` for CRUD.
- Decompose logic into `Support/` components (Handlers, Mappers, Guards).
- Use **constructor injection** for all dependencies (No static calls).
- Register in `app/Config/Services.php`.

### Step 3: Declarative Controller
- Extend `ApiController`.
- Use `handleRequest()` for automatic mapping and context propagation.

---

## 4. Exception Reference (HasStatusCode)

Exceptions should implement `HasStatusCode`:
- `NotFoundException` (404)
- `AuthenticationException` (401)
- `AuthorizationException` (403)
- `ValidationException` (422)
- `BadRequestException` (400)

---

## 5. Security & Style

- ✅ **Immutability:** PHP 8.2 `readonly class` mandatory.
- ✅ **Atomic:** Use `HandlesTransactions` for state changes.
- ✅ **Context:** Access identity via `SecurityContext` injected in service methods.
- ✅ **i18n:** Use `lang()` helper. Provide `en` and `es` files.

---

## Quick Commands

```bash
php spark make:crud {Name} --domain {Domain} --route {endpoint}
php spark swagger:generate
composer quality
vendor/bin/phpunit
```
