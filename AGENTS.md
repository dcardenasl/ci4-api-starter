# Repository Guidelines for AI Agents

## Project Structure & Module Organization
- `app/DTO/` contains the data contract layer (Request/Response DTOs).
- `app/Controllers/Api` handles HTTP I/O and maps data to DTOs.
- `app/Services/` implements pure business logic decoupled from HTTP.
- `app/Interfaces/` defines service contracts.
- `app/Models/` and `app/Entities/` manage database persistence.
- `tests/` holds PHPUnit tests split into `Unit/`, `Integration/`, and `Feature/` suites.

## Core Development Principles (DTO-First)
- **Mandatory DTOs:** All data transfer between controllers and services must use PHP 8.2 `readonly` classes.
- **Pure Services:** Services must not use `ApiResponse` or status codes. Read flows return DTOs; command flows return `OperationResult`.
- **Auto-Validation:** Request DTOs validate input in their constructors through `BaseRequestDTO` rules.
- **Living Docs:** OpenAPI schemas are defined as attributes directly in DTO classes.

## Build, Test, and Development Commands
- `composer install` installs dependencies.
- `php spark serve` runs the dev server.
- `php spark migrate` applies migrations.
- `php spark make:crud {Resource} --domain {Domain} --route {endpoint}` scaffolds new resources (Mandatory first step).
- `vendor/bin/phpunit` runs the test suite.
- `composer quality` runs all quality gates (PHPStan, tests, CS-check).
- `php spark swagger:generate` regenerates `public/swagger.json` from DTO and Documentation annotations.

## Agent Critical Rules
- **Thin Controllers:** Define `protected string $serviceName` and use `handleRequest('method', RequestDTO::class)` as the default pattern.
- **No Inline Annotations:** Do not add OpenAPI annotations to controllers. Use DTOs for schemas and `app/Documentation/` for endpoints.
- **Service Registration:** Always register new services in `app/Config/Services.php`.
- **Pure Logic:** Business decisions belong in services; HTTP decisions belong in controllers.

### Controller Architecture Invariants
- API controllers must extend `ApiController`.
- Standard endpoints must use `handleRequest()`.
- Use `handleRequest(..., RequestDTO::class)` to ensure input data is validated before reaching the service.
- Automatic normalization in `ApiController::respond()` ensures DTOs are converted to API JSON format.

## Testing Guidelines
- Unit tests for services should assert against DTO return types.
- Feature tests should verify the final JSON response structure.
- Always use mocks for external dependencies in unit tests.

## Security Mandates
- Never commit `.env` files.
- Ensure sensitive fields are sanitized in `AuditService`.
- Use `readonly` classes for all data structures to ensure inmutability.
