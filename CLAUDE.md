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
php spark make:crud {Name} --domain {Domain} --route {endpoint}  # Scaffold new CRUD (Mandatory)
```

### OpenAPI Documentation
```bash
php spark swagger:generate      # Generate public/swagger.json from DTOs and app/Documentation/
```

## Architecture Overview (Modernized DTO-First)

This is a **Declarative DTO-First Layered REST API** following the pattern: **Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO]**.

### Key Design Principles

1. **DTO-First Shield:** Data validation is an intrinsic property of the DTO. Request DTOs must extend `BaseRequestDTO`.
2. **Auto-Validation:** The `BaseRequestDTO` constructor handles validation automatically via `rules()`. If an object exists, it is valid.
3. **Pure & Transactional Services:** Services extend `BaseCrudService`, are agnostic to HTTP, and use the `HandlesTransactions` trait.
4. **Declarative Controllers:** Controllers extend `ApiController` and use `handleRequest()` to orchestrate the flow without boilerplate.
5. **Output Normalization:** `ApiController` wraps normalized service outcomes and maps paginated DTO shapes to canonical paginated responses.

## Implementation Guidelines

### 1. Request DTOs (`app/DTO/Request/`)
- Must extend `BaseRequestDTO`.
- Implement `rules()` and `map(array $data)`.
- Use PHP 8.2 `readonly` classes.
- **NO manual validation calls in services.**

### 2. Response DTOs (`app/DTO/Response/`)
- Define the contract for the client. Include OpenAPI attributes.
- Use `fromArray(array $data)` static method.

### 3. Services (`app/Services/`)
- Extend `BaseCrudService` for standard CRUD.
- Use `HandlesTransactions` trait for state changes.
- Return DTOs for read workflows and `OperationResult` for command-style workflows. Throw exceptions for errors.
- Implement `applyBaseCriteria()` for global security filters.

### 4. Controllers (`app/Controllers/Api/V1/`)
- Must extend `ApiController`.
- Use declarative handling: `return $this->handleRequest('methodName', RequestDTO::class);`.

### 5. Documentation
- Schemas live in DTOs. Endpoints live in `app/Documentation/{Domain}/`.

## Testing Strategy

### Unit Tests
- **Services:** Test logic by asserting against DTO return types. Mock dependencies.
- **DTOs:** Test that the constructor throws `ValidationException` for invalid data.

### Feature/Integration Tests
- Verify JSON structure and status codes (201 for creation, 202 for pending, 422 for validation).

## Common Pitfalls (DO NOT DO)
- ❌ Using `InputValidationService` or `validateOrFail` manual calls (Legacy).
- ❌ Returning `ApiResponse` from a service.
- ❌ Passing raw arrays to service methods.
- ❌ Not using `wrapInTransaction` for state-changing operations.

## Single Source of Truth

For architecture rules and onboarding, prefer:

1. `docs/template/ARCHITECTURE_CONTRACT.md`
2. `docs/template/MODULE_BOOTSTRAP_CHECKLIST.md`
3. `docs/template/QUALITY_GATES.md`
