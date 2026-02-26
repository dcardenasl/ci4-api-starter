# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Server
```bash
php spark serve                  # Start dev server at http://localhost:8080
```

### Testing
```bash
# Run all tests
vendor/bin/phpunit
vendor/bin/phpunit --testdox    # Human-readable test output

# Run specific test suites
vendor/bin/phpunit tests/Unit              # Unit tests (fast, no DB)
vendor/bin/phpunit tests/Integration       # Integration tests (with DB)
vendor/bin/phpunit tests/Feature           # Feature/Controller tests (HTTP)

# Composer aliases
composer quality                # Run all quality checks (PHPStan, PHPUnit, etc.)
composer cs-fix                 # Fix code style (PSR-12)
```

### Database
```bash
php spark migrate               # Run migrations
php spark migrate:refresh       # Rollback all + re-run migrations
php spark make:crud {Name} --domain {Domain} --route {endpoint}  # Scaffold new CRUD (Mandatory first step)
```

### OpenAPI Documentation
```bash
php spark swagger:generate      # Generate public/swagger.json from DTOs and app/Documentation/
```

## Architecture Overview (Million-Dollar Standard)

This is a **DTO-First Layered REST API** following the pattern: **Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO]**.

### Key Design Principles

1. **DTO-First:** All data entering or leaving the service layer must use PHP 8.2 `readonly` classes.
2. **Auto-Validation:** Request DTOs must validate themselves in the constructor via `validateOrFail()`.
3. **Pure Services:** Services must be agnostic to HTTP/API concerns (no `ApiResponse`, no status codes).
4. **Output Normalization:** `ApiController` automatically normalizes DTOs to snake_case associative arrays for JSON output.
5. **Living Documentation:** OpenAPI schemas (`#[OA\Schema]`) are defined directly in DTO classes.

### Request/Response Flow

```
HTTP Request → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiController::respond() → JSON
```

## Implementation Guidelines

### 1. Request DTOs (`app/DTO/Request/`)
- Always use `readonly class`.
- Call `validateOrFail($data, 'domain', 'action')` in the constructor.
- Properties should be strictly typed.

### 2. Response DTOs (`app/DTO/Response/`)
- Include OpenAPI `#[OA\Property]` attributes on constructor properties.
- Use `fromArray(array $data)` static method for instantiation from Entities/Models.
- Properties should use camelCase (automatically mapped to snake_case in JSON).

### 3. Services (`app/Services/`)
- Must implement an interface in `app/Interfaces/`.
- Must return DTO objects or Entities (never `ApiResponse`).
- Throw custom exceptions for errors.

### 4. Controllers (`app/Controllers/Api/V1/`)
- Must extend `ApiController`.
- Use `$this->getDTO(YourRequestDTO::class)` to map request data.
- Use `$this->handleRequest(fn() => ...)` to delegate to services.

### 5. Documentation
- Do NOT use annotations in controllers.
- Schemas live in DTOs. Endpoints live in `app/Documentation/{Domain}/`.

## Testing Strategy

### Unit Tests
- **Services:** Test by asserting against DTO return types. Mock all dependencies.
- **DTOs:** Test auto-validation by passing invalid data and expecting `ValidationException`.

### Feature/Integration Tests
- Use `CustomAssertionsTrait` ONLY to verify the final JSON structure and `status` field.

## Security Mandates
- **XSS:** Handled automatically by `ApiController`.
- **Audit:** Use `AuditService` for all state-changing operations.
- **SQL Injection:** Always use CI4 Query Builder.
- **Passwords:** Never expose in DTOs or responses.

## Common Pitfalls
- Returning `ApiResponse` from a service.
- Using generic arrays instead of DTOs for service parameters.
- Forgetting to run `php spark swagger:generate` after changing DTO properties.
- Not using `readonly` classes for data structures.
