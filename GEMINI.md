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
- `php spark db:seed InitialSeeder`: Seed initial data.

### Testing & Quality
- `vendor/bin/phpunit`: Run all tests.
- `composer quality`: Run full quality suite (`cs-check`, `phpstan`, `i18n-check`, `phpunit`).
- `composer cs-fix`: Automatically fix coding style issues.

## Development Workflows

### 1. DTO-First Development (The "Shield" Pattern)
**All data transfer must use PHP 8.2 `readonly` classes.**
- **Request DTOs:** Must extend `BaseRequestDTO`. Perform all validation in the `rules()` method. The constructor handles automatic validation. 
- **NO Manual Validation:** The `InputValidationService` has been removed. Never use it.
- **Response DTOs:** Define exactly what the client receives. Include OpenAPI `#[OA\Property]` attributes here.

### 2. Controller Standards (The Orchestrator)
- Extend `ApiController`.
- Use `handleRequest('serviceMethod', RequestDTO::class)` for declarative handling.
- **Indirection:** Never handle business logic or validation in the controller.
- **Normalization:** `ApiController` automatically wraps responses in `ApiResponse::success()` and handles `data` keying.

### 3. Pure Service Layer (The Engine)
- Services MUST be "pure" (agnostic to HTTP/API).
- **Inheritance:** Standard CRUD services should extend `BaseCrudService`.
- **Atomic Operations:** Use `HandlesTransactions` trait for any method that modifies state.
- **NO `ApiResponse` inside services.**
- **Error Handling:** Throw custom exceptions (`NotFoundException`, `ValidationException`, etc.).

### 4. Living Documentation (OpenAPI)
- **Schemas:** Defined as attributes in DTO classes (`#[OA\Schema]`).
- **Endpoints:** Defined in `app/Documentation/{Domain}/`.
- **Sync:** Documentation must always match the DTO properties.

### 5. Testing Strategy
- **Unit:** Test services by asserting against DTO return types. Use mocks for all dependencies.
- **Feature/Integration:** Use `CustomAssertionsTrait` ONLY here to verify the final JSON response structure and status codes.

## Security Mandates
- **Inmutability:** Use `readonly` for all DTOs and injected service properties.
- **Audit Trail:** Audit is automated via `Auditable` trait in models.
- **SQL Injection:** Always use CI4 Query Builder; avoid raw SQL.
- **Secret Protection:** Never commit `.env` files.
