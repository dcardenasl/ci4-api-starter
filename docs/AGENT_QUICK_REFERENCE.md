# Agent Quick Reference - CI4 API Starter (Million-Dollar Architecture)

**Purpose**: Essential patterns and conventions for AI agents implementing CRUD resources using the DTO-first architecture.

---

## 1. Request Flow (DTO-First)

```
HTTP Request → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiController::respond() → JSON
```

---

## 2. CRUD Implementation Checklist

### Step 0: Scaffold First (Required)
```bash
php spark make:crud {Name} --domain {Domain} --route {endpoint}
```

### Step 1: Migration, Entity & Model
- **Migration:** Include `id`, `created_at`, `updated_at`, `deleted_at`.
- **Entity:** Proper `$casts` and `$dates`.
- **Model:** Use `Filterable, Searchable, Auditable`. Define `$allowedFields` and `$validationRules`.

### Step 2: Request DTO (`app/DTO/Request/`)
- PHP 8.2 `readonly` class.
- Auto-validation in constructor via `BaseRequestDTO` (`rules()` + `messages()` + `map()`).

### Step 3: Response DTO (`app/DTO/Response/`)
- PHP 8.2 `readonly` class.
- OpenAPI integrated: `#[OA\Schema]` and `#[OA\Property]`.
- Static method `fromArray(array $data)` for Entity mapping.

### Step 4: Pure Service Layer
- **Interface:** `app/Interfaces/{Name}ServiceInterface.php`.
- **Implementation:** Return DTOs (for reads) or `OperationResult` (for command-style outcomes). **NO `ApiResponse`**.
- Register in `app/Config/Services.php`.
- For standard CRUD resources, extend `CrudServiceContract` and `BaseCrudService`.

### Step 5: Controller
- Extend `ApiController`.
- Use `handleRequest('methodName', RequestDTO::class)` for standard endpoints.
- Use closure form only when route params must be combined with service calls (`show`, `update`, `delete` by id).

### Step 6: Testing
- **Unit:** Assert against DTO return types. Mock dependencies.
- **Feature:** Verify JSON structure via `CustomAssertionsTrait`.

---

## 3. Exception Reference

- `NotFoundException` (404)
- `AuthenticationException` (401)
- `AuthorizationException` (403)
- `ValidationException` (422)
- `BadRequestException` (400)
- `ConflictException` (409)

---

## 4. Automatic Normalization

The `ApiController` automatically:
1. Wraps service results in `ApiResponse::success()`.
2. Recursively converts DTOs to arrays.
3. Maps camelCase properties to snake_case JSON keys.
4. Detects paginated DTO shape (`data`, `total`, `page`, `perPage`) and emits canonical paginated response with `meta`.

---

## 5. Security & Style

- ✅ **Immutability:** Use `readonly` for all DTOs and injected properties.
- ✅ **i18n:** Always use `lang()` helper. Provide `en` and `es` files.
- ✅ **SQL Injection:** Always use CI4 Query Builder.
- ✅ **Coding Style:** PSR-12 enforced via `composer cs-fix`.

---

## Quick Commands

```bash
php spark make:crud {Name} --domain {Domain} --route {endpoint}
php spark swagger:generate
composer quality
vendor/bin/phpunit
```
