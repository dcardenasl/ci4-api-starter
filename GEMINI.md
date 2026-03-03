# GEMINI.md - Project Context & Instructions

This file provides the foundational context and operational mandates for working within the **CodeIgniter 4 API Starter Kit**. Always adhere to these modern, high-stakes architecture standards.

## Project Overview

A production-ready REST API starter template for CodeIgniter 4 (v4.6+) with an advanced **DTO-first architecture**, strict typing, and comprehensive test coverage.

- **Primary Stack:** PHP 8.2+, MySQL 8.0+, CodeIgniter 4.
- **Architecture:** Layered REST API: **Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO]**.
- **Core Features:** JWT Authentication (with revocation), RBAC (User/Admin/Superadmin), File Management (Multipart/Base64), Audit Trail, Health Checks, Rate Limiting.

## Essential Commands

### Development & Server
- `php spark serve`: Start the development server at `http://localhost:8080`.
- `php spark swagger:generate`: Generate `public/swagger.json` from DTO schemas and endpoint definitions.

### Database & Migrations
- `php spark migrate`: Run pending migrations.
- `php spark make:crud {Name} --domain {Domain} --route {endpoint}`: Generate CRUD skeleton (recommended default).
- `php spark module:check {Name} --domain {Domain}`: Validate scaffolded module artifacts.
- `php spark db:seed InitialSeeder`: Seed initial data.

### Testing & Quality
- `vendor/bin/phpunit`: Run all tests.
- `composer quality`: Run full quality suite (`cs-check`, `phpstan`, `i18n-check`, `phpunit`).
- `composer cs-fix`: Automatically fix coding style issues.

## Development Workflows

### 1. Domain-Driven Services (New Standard)
**All services must reside in a domain subdirectory.**
- **Composition over Inheritance:** Decompose logic into `Support/` classes (Handlers, Mappers, Guards).
- **Immutability:** Use PHP 8.2 `readonly class` for all new services and DTOs.
- **Strict DI:** Injected dependencies must be typed via Interfaces. No static calls to `Config\Services`.

### 2. DTO-First Development (The "Shield" Pattern)
**All data transfer must use PHP 8.2 `readonly` classes.**
- **Automatic Context:** `ApiController` enriches DTO payloads with `user_id` and `user_role` using `SecurityContext` before DTO construction.
- **NO Manual Validation:** Handled by DTO constructor.

### 3. Controller Standards (The Orchestrator)
- Extend `ApiController`.
- **Pure Orchestration:** `ApiController` now uses `ApiResponse::fromResult()` and `ExceptionFormatter` to normalize all outputs via the `ApiResult` value object.
- **Standard Return:** Service results are automatically wrapped in `ApiResponse::success()` or `ApiResponse::paginated()`.

### 4. Pure Service Layer (The Engine)
- **Domain Logic Only:** Services must not touch global request state.
- **Atomic Operations:** Use `HandlesTransactions` trait for state changes.
- **Error Handling:** Throw exceptions implementing `HasStatusCode`.
- **Repository Strategy:** Use `GenericRepository` as default for CRUD and escalate to dedicated repositories only when domain queries are non-trivial.

### 5. Living Documentation (OpenAPI)
- **Schemas:** Defined as attributes in DTO classes (`#[OA\Schema]`).
- **Sync:** Documentation must always match the DTO properties.

### 5. Testing Strategy
- **Unit:** Test services by asserting against DTO return types. Use mocks for all dependencies.
- **Feature/Integration:** Use `CustomAssertionsTrait` ONLY here to verify the final JSON response structure and status codes.

## Security Mandates
- **Inmutability:** Use `readonly` for all DTOs and injected service properties.
- **Audit Trail:** Audit is automated via `Auditable` trait in models.
- **SQL Injection:** Always use CI4 Query Builder; avoid raw SQL.
- **Secret Protection:** Never commit `.env` files.

## CRUD Lifecycle Notes
- `make:crud` does not generate migration files.
- Create migrations immediately after scaffold/module validation and before closing service/domain behavior.
