# Changelog

All notable changes to ci4-api-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] — 2026-05-03

### ⚠️ Breaking Changes
- **`users.role` column removed** from schema, model, DTOs, JWT, security context, and login/refresh/OAuth response payloads. Authorization is now membership-driven.
- **JWT contract changed**: `role` claim removed; new `scope: string[]` claim carries the user's effective permission codes.
- **Login/refresh response shape changed**: the `user` object no longer exposes `role`; it now exposes `permissions: string[]`.
- **`RoleAuthorizationFilter` (`userroleguard`) removed**. Replaced by the fine-grained `permission:<code>` filter.
- **Permission code separator is `.` (dot), not `:` (colon)** — e.g. `users.write`, `iam.admin-access`. Reason: CI4's `Filters::getCleanName()` splits on `:` without a limit, so `permission:users:write` was silently truncated to filter `permission` with arg `users`.
- **`users:bootstrap-superadmin` requires the RBAC seeder first**. Run `php spark db:seed RbacBootstrapSeeder` before bootstrapping the superadmin; the command now attaches the `superadmin` role via an `app_user_memberships` row instead of writing the (removed) `users.role` column.
- **In-tree CRUD scaffolding commands removed** (`app/Commands/MakeCrud.php`, `MakeCrudRemove.php`, `ModuleCheck.php`). Scaffolding is now provided by the `dcardenasl/ci4-api-crud-maker` Composer package; consumer config lives in `app/Config/Scaffolding.php`.

### Added
- Granular RBAC/IAM model with six tables: `applications`, `permissions`, `roles`, `role_permissions`, `app_user_memberships`, `membership_roles`
- `RbacBootstrapSeeder` — idempotent seeder for the `self` application, the canonical permission set (`users.read/write`, `files.read/write`, `audit.read`, `metrics.read`, `apikeys.read/write`, `iam.admin-access`, `iam.superadmin-access`), and the three system roles (`superadmin`, `admin`, `user`)
- IAM REST endpoints under `/api/v1/iam/` (all gated by `permission:iam.admin-access`):
  - `roles` CRUD + `roles/{id}/permissions` (list / attach / detach)
  - `permissions` CRUD
  - `memberships` CRUD + `memberships/{id}/roles` (list / attach / detach)
  - `applications` (read-only)
  - `users/{user_id}/memberships` and `users/{user_id}/permissions?application_id=N` (effective permissions)
- `EffectivePermissionsResolver` — derives a user's effective permission codes from active memberships → roles → role permissions
- `permission:<code>` route filter for fine-grained authorization
- `permissions: string[]` field on the `user` object of login/refresh responses, sourced from the resolver
- `scope: string[]` JWT claim carrying the same effective permission codes
- `iam:smoke-test` Spark command for IAM end-to-end verification
- IAM-specific OpenAPI documentation (`app/Documentation/Iam/`)

### Changed
- `users:bootstrap-superadmin` now creates the user and attaches the `superadmin` role via an `app_user_memberships` row (no more `users.role` write)
- `init.sh` chains `migrate → db:seed RbacBootstrapSeeder → swagger:generate → users:bootstrap-superadmin`
- File access control: admin override now checks the `files.read` permission (previously the legacy role); `FileService::findById` enforces ownership at the data layer
- Authentication flows (`login`, `refresh`, OAuth) no longer carry `role` through the pipeline
- CRUD scaffolding migrated to the external `dcardenasl/ci4-api-crud-maker` package (referenced by VCS); `vendor/bin/make-crud.sh` is the entry point and now emits `permission:iam.admin-access` for the protected route group of generated modules
- IAM migration timestamps realigned with the project's chronological migration order
- `symfony/mailer` and remaining transitive dependencies bumped to latest patch/minor

### Fixed
- `FileService::findById` enforces the ownership check that was previously only applied at the index layer
- Scaffolding rollback now restores pre-existing files instead of deleting them when generation aborts
- Test suite aligned with permission-based authorization (no remaining references to the removed `role` column or `RoleAuthorizationFilter`)

### Removed
- `users.role` column and all related code paths (DTO fields, model casts, seeders, JWT claim, security context property, login/refresh/OAuth response field)
- `RoleAuthorizationFilter` and the `userroleguard` filter alias
- `UserRoleGuard` indirection
- `AuditFacetsRequestDTO` (unused)
- In-tree scaffolding commands (`MakeCrud`, `MakeCrudRemove`, `ModuleCheck`) — now provided by `dcardenasl/ci4-api-crud-maker`

### Migration Guide

Existing deployments upgrading from `1.4.x` must:

1. Pull the new code and run `composer install`.
2. Run database migrations: `php spark migrate` (six new IAM tables + drop of `users.role`).
3. Seed the RBAC baseline (idempotent): `php spark db:seed RbacBootstrapSeeder`.
4. Re-attach roles to existing users by inserting `app_user_memberships` + `membership_roles` rows (or rerun `php spark users:bootstrap-superadmin` for the first superadmin).
5. Update any custom routes that used `userroleguard` / `roleAuth:` filters to `permission:<code>` — note the dot separator.
6. Update API clients that read `user.role` from the login response or the `role` claim from the JWT to consume `user.permissions[]` and the `scope` claim instead.

## [1.4.0] — 2026-04-30

### Added
- `env:check` Spark command to validate all required environment variables at startup
- Swagger UI served at `/api/docs` in non-production environments
- `FILES_USER_SCOPED` configuration toggle — when enabled, users can only access their own files
- `GET /files/:id/info` endpoint to retrieve file metadata without downloading
- Derived `category` field on `FileResponseDTO` computed from MIME type
- Filter array support in `FileIndexRequestDTO` (multi-value category and MIME filters)
- `avatar_url` field on the user update endpoint
- `bin/validate-crud.sh` to verify completeness of generated CRUD artifacts
- Dependabot configuration for automated Composer security updates

### Changed
- PHPUnit upgraded from `^10.5.16` to `^11.0`; removed deprecated `beStrictAboutOutputDuringTests` from `phpunit.xml`
- PHPStan upgraded from `^1.10` to `^2.0`; added `treatPhpDocTypesAsCertain: false` to `phpstan.neon`
- Scaffolding engine hardened: six critical generation bugs fixed, tests now pass without `markTestIncomplete`, `StringHelper::studly` preserves internal capitals, `TypeMapper` corrected for `int`/`bool`/`date` types, architectural consistency enforced on every generated CRUD
- Catalog reference module removed

### Fixed
- Sort parameter now enabled in all `IndexRequestDTO` classes

## [1.3.4] — 2026-04-09

### Fixed
- **(PR #12)** Handled missing `timeout` command on macOS in the health-check loop
- **(PR #13)** Improved POSIX compliance across install scripts; hardened Docker port detection to avoid false-positive conflicts

## [1.3.3] — 2026-04-09

### Added
- **(PR #11)** Optional superadmin bootstrap step in `init.sh` with input validation and failure diagnostics

## [1.3.2] — 2026-04-09

### Fixed
- **(PR #10)** Fixed crash in Docker mode when the log directory was missing or unwritable

## [1.3.1] — 2026-04-09

### Fixed
- **(PR #9)** Fail-fast on setup errors; improved bootstrap diagnostics and failure cleanup; hardened Docker MySQL detection and credential handling

## [1.3.0] — 2026-04-09

### Added
- **(PR #8)** Docker MySQL database provisioning from `install.sh`; shared setup library extracted to eliminate duplication between init and install scripts

## [1.2.0] — 2026-04-09

### Added
- **(PR #7)** Token hashing at rest for password-reset and email-verification tokens; security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`); high-entropy test-bypass authentication; enhanced payload sanitization for secure logging; tightened API access policies

## [1.1.0] — 2026-03-17

### Added
- **(PR #6)** One-command interactive installer (`init.sh`) with environment bootstrapper; project metadata configuration consumed by OpenAPI, routes, and services; scaffolding now generates route file updates, language stubs, and OpenAPI attributes on DTOs; regression test suite for the scaffolding orchestrator

## [1.0.0] — 2026-03-11

### Added
- **(PR #1)** Initial REST API starter: JWT authentication (access + refresh tokens, JTI revocation), Docker support (multi-stage Dockerfile + docker-compose with MySQL), Swagger/OpenAPI documentation, GitHub Actions CI pipeline, unit tests, and security hardening
- **(PR #2)** Standardised `ApiResponse` response envelope, GitHub template repository configuration, improved Swagger documentation coverage
- **(PR #4)** JWT refresh tokens with cache-aware TTL revocation; file management with local and AWS S3 backends (stream-based uploads); advanced pagination, filtering, and full-text search; email system (Symfony Mailer) with queue infrastructure and job workers; audit trail with severity levels, security context, and entity logging; per-IP and per-user throttling; locale detection from `Accept-Language`; Google OAuth with pending-approval lifecycle; user invitation and approval workflows; superadmin role with `php spark superadmin:create`
- **(PR #5)** Full DTO-first layered architecture rewrite (`BaseRequestDTO` auto-validation, `BaseCrudService` with transaction handling, `ApiController::handleRequest()` declarative pipeline); CRUD scaffolding engine (`bin/make-crud.sh` generating DTOs, services, controllers, migrations, routes, language files, and tests); repository pattern with `GenericRepository`; API key management with rate-limit strategy; feature toggle filter; domain events; OpenAPI auto-generation (`php spark swagger:generate`); PHPStan level 7, PHP-CS-Fixer PSR-12, pre-commit hooks; 25 architecture guardrail tests; CI/CD pipeline with Swagger validation and coverage enforcement

### Fixed
- **(PR #3)** Repository cleanup: removed dead code and stale files

[unreleased]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.4.0...v2.0.0
[1.4.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.3.4...v1.4.0
[1.3.4]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.3.3...v1.3.4
[1.3.3]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/dcardenasl/ci4-api-starter/releases/tag/v1.0.0
