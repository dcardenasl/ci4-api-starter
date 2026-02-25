# GEMINI.md - Project Context & Instructions

This file provides the foundational context and operational mandates for working within the **CodeIgniter 4 API Starter Kit**. Always adhere to these patterns and standards.

## Project Overview

A production-ready REST API starter template for CodeIgniter 4 (v4.6+) with a clean, layered architecture and comprehensive test coverage.

- **Primary Stack:** PHP 8.1+, MySQL 8.0+, CodeIgniter 4.
- **Key Technologies:** JWT (Firebase JWT), AWS SDK (for S3), Flysystem, Swagger/OpenAPI (zircote/swagger-php).
- **Architecture:** Layered REST API: **Controller → Service → Model → Entity**.
- **Core Features:** JWT Authentication (with revocation), RBAC (User/Admin/Superadmin), File Management, Audit Trail, Health Checks, Rate Limiting.

## Essential Commands

### Development & Server
- `php spark serve`: Start the development server at `http://localhost:8080`.
- `php spark routes`: List all registered routes.
- `php spark key:generate`: Generate an encryption key for `.env`.

### Database & Migrations
- `php spark migrate`: Run pending migrations.
- `php spark migrate:rollback`: Rollback the last migration batch.
- `php spark migrate:refresh`: Rollback all and re-run migrations.
- `php spark users:bootstrap-superadmin --email user@example.com --password 'Pass123!'`: Create the initial superadmin.

### Testing & Quality
- `vendor/bin/phpunit`: Run all tests.
- `vendor/bin/phpunit tests/Unit`: Run fast unit tests (no DB).
- `vendor/bin/phpunit tests/Integration`: Run integration tests (with DB).
- `vendor/bin/phpunit tests/Feature`: Run end-to-end HTTP tests.
- `composer quality`: Run full quality suite (`cs-check`, `phpstan`, `i18n-check`, `phpunit`).
- `composer cs-fix`: Automatically fix coding style issues.

### Documentation
- `php spark swagger:generate`: Generate `public/swagger.json` from definitions in `app/Documentation/`.

## Development Workflows

### Adding New CRUD Resources
**Mandatory:** Use the scaffold command first:
`php spark make:crud {Name} --domain {Domain} --route {endpoint}`

### Controller Standards
- **Business API Controllers:** Must extend `ApiController`. They use `handleRequest()` to delegate to services and benefit from centralized exception handling and XSS sanitization.
- **Infrastructure Controllers:** (e.g., `HealthController`, `MetricsController`) extend the base `Controller` for maximum performance and public accessibility. **Do not use `ApiController` for infrastructure.**

### Service Layer & Errors
- Services MUST implement an interface (in `app/Interfaces/`).
- Services return standardized arrays via `ApiResponse` library (e.g., `ApiResponse::success($data)`).
- **Error Handling:** Services throw custom exceptions (`NotFoundException`, `ValidationException`, etc.). The `ApiController` automatically catches these and converts them to the correct HTTP response.

### Documentation (OpenAPI)
- **DO NOT** use annotations/decorators in controllers.
- **DO** define schemas and endpoints in `app/Documentation/{Domain}/`.

### Testing Strategy
- **Unit:** Mock all dependencies. Use anonymous classes for CI4 Models to mock query builder methods.
- **Integration:** Real DB. Always set `protected $namespace = 'App'` in integration tests to ensure app migrations are used.
- **Feature:** Full HTTP stack.
- **CustomAssertionsTrait:** Use ONLY for tests verifying `ApiResponse` structure (Service and Feature tests).

## Configuration Requirements
Ensure the following are set in `.env`:
- `JWT_SECRET_KEY`: Minimum 64 characters.
- `encryption.key`: Use `php spark key:generate`.
- `database.default.*`: MySQL credentials.

## Security Mandates
- **XSS Protection:** Handled by `ApiController` but always validate input.
- **SQL Injection:** Always use CI4 Query Builder; avoid raw SQL.
- **Passwords:** Never expose passwords in API responses (handled by `Entity` `$attributes`).
- **Secret Protection:** Never commit `.env` files or hardcode keys. Use the rotation procedures in `README.md` if compromised.
