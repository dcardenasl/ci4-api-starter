# Changelog

All notable changes to ci4-api-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] — 2026-05-13

This release replaces the legacy single-role authorization model with a granular RBAC/IAM system, externalises the runtime foundation and the CRUD scaffolding engine into two Composer packages (`dcardenasl/ci4-api-core` and `dcardenasl/ci4-api-scaffolding`), and adds production-grade operational concerns: API versioning policy, idempotency keys, RFC 7807 Problem Details, correlation IDs, a maintenance-mode short-circuit, environment validation, and a tag-driven GitHub Release workflow.

### ⚠️ Breaking Changes

- **`users.role` column removed** from schema, model, DTOs, JWT, security context, and login/refresh/OAuth response payloads. Authorization is now role-driven through the `user_roles` join (see RBAC/IAM model below).
- **JWT contract changed**: the `role` claim is gone; a new `scope: string[]` claim carries the user's effective permission codes.
- **Login/refresh response shape changed**: the `user` object no longer exposes `role`; it exposes `permissions: string[]` instead.
- **`RoleAuthorizationFilter` (`userroleguard`) removed**. Replaced by the fine-grained `permission:<code>` filter.
- **Permission code separator is `.` (dot), not `:` (colon)** — e.g. `users.write`, `iam.admin-access`. CI4's `Filters::getCleanName()` splits filter strings on `:` without a limit, so `permission:users:write` was silently truncated to filter `permission` with arg `users`.
- **`users:bootstrap-superadmin` requires the RBAC seeder first**. Run `php spark db:seed RbacBootstrapSeeder` before bootstrapping the superadmin; the command now attaches the `superadmin` role via a `user_roles` row instead of writing the (removed) `users.role` column.
- **Runtime base classes ship in `dcardenasl/ci4-api-core`** (namespace `dcardenasl\Ci4ApiCore\…`). `ApiController`, `BaseCrudService`, `BaseRepository`, `BaseRequestDTO`, `BaseAuditableModel`, `ApiException` family, `ApiResponse`, `ApiResult`, `OperationResult`, `HandlesTransactions`, `Auditable`, `ContextHolder`, the HTTP filter stack, mappers, and the audit chain are no longer duplicated in `app/`. Consumers that extended in-tree classes must update their imports to the `dcardenasl\Ci4ApiCore\…` namespace.
- **In-tree CRUD scaffolding commands removed** (`MakeCrud`, `MakeCrudRemove`, `ModuleCheck`). Scaffolding is now provided by `dcardenasl/ci4-api-scaffolding` (a `require-dev` Composer package, formerly `ci4-api-crud-maker`); the shell wrapper is `vendor/bin/make-crud.sh` and consumer configuration lives in `app/Config/Scaffolding.php`.
- **`Config\Api::$accessPolicyBypassRoutes` replaces the hardcoded `JwtAuthFilter` bypass list.** Custom bypass entries must move from filter source to config; the default still allows `api/v1/auth/resend-verification`, the only route that legitimately skips account-policy checks.
- **`Config\Cors` refuses to boot in production** when both `CORS_ALLOWED_ORIGINS` and `app.baseURL` are empty (previous behaviour: silent empty-origin list and a confusing 4xx storm).
- **PHPStan promoted to level 8**, with `phpstan-baseline.neon` capturing ~125 historical type-debt errors. New code must pass cleanly against level 8; the baseline shrinks incrementally.
- **`users:bootstrap-superadmin` requires `RbacBootstrapSeeder` to have run.** The seeded `superadmin` role is the source of the attached `user_roles` row.

### Added

#### RBAC / IAM model
- **Five-table RBAC schema** (`applications`, `permissions`, `role_permissions`, `roles`, `user_roles`) with single seeded application `self` (`id=1`). Permissions are application-scoped (`permissions.application_id`); roles are global. The 2.0 cycle introduced the legacy `app_user_memberships` + `membership_roles` pair internally and then consolidated them into `user_roles`; consumers landing on 2.0 directly see only the final schema.
- **`RbacBootstrapSeeder`** — idempotent seeder for the `self` application, the canonical permission set (`users.read/write`, `files.read/write`, `audit.read`, `metrics.read`, `apikeys.read/write`, `iam.admin-access`, `iam.superadmin-access`) and the three system roles (`superadmin`, `admin`, `user`).
- **`EffectivePermissionsResolver`** — derives a user's effective permission codes from `user_roles → roles → role_permissions → permissions`.
- **`permission:<code>` route filter** for fine-grained authorization.
- **`permissions: string[]` field** on the `user` object of login/refresh responses, sourced from the resolver.
- **`scope: string[]` JWT claim** carrying the same effective permission codes.
- **IAM REST endpoints** under `/api/v1/iam/` (all gated by `permission:iam.admin-access`): `roles` CRUD + `roles/{id}/permissions`, `permissions` CRUD, `users/{user_id}/permissions?application_id=N`. Role assignment to users is performed via the existing `Users` resource (`role_ids[]` payload).
- **`iam:smoke-test`** Spark command for IAM end-to-end verification.
- **IAM-specific OpenAPI documentation** under `app/Documentation/Iam/`.
- **`AssignableRolesService`** — extracts the anti-escalation logic from `UserController::assignableRoles()`. A role is assignable iff every permission code attached to that role is already in the actor's effective set.

#### API governance — ADRs and policy
- **API versioning policy** (ADR-008): `Config\Api::$apiVersions` map (`v1`, `v2`, …) with `status` / `deprecated_at` / `sunset_at` / `successor` per entry. `DeprecationHeadersFilter` emits `Deprecation`, `Sunset`, and `Link: rel="successor-version"` headers (alias `deprecationheaders`, `globals.after`). `GET /api/versions` returns `{current, versions:[…]}` — public, unauthenticated, no version prefix.
- **`Idempotency-Key` opt-in support** (ADR-009, RFC-style retry safety): migration `2026-05-06-100000_CreateIdempotencyKeysTable`, `IdempotencyFilter` alias `idempotency` (per-route, not global). 400 on malformed key, replay with `Idempotent-Replay: true` on cache hit + matching body hash, `409 Conflict` with `Idempotency-Mismatch: true` on hit + body mismatch, persist only on 2xx, race-safe insert.
- **RFC 7807 Problem Details** (ADR-010): additive helpers on `ApiResponse` — `problemDetails(...)`, `negotiateError(...)`, `clientPrefersProblemJson(...)`. Default error envelope preserved untouched; controllers opt in via content negotiation when the client sends `Accept: application/problem+json`.
- **ADR-011 — Multi-tenancy out-of-scope for v1.x/v2.x** (EN+ES). The kit is single-tenant; tenancy requires a fork and a documented surface change.
- **ADR-012 — Config values resolve at boot, not runtime** (EN+ES). The `Config\Api` constructor pattern is contract; env values are read once at boot and treated as immutable for the request lifetime.

#### Operational / observability
- **`CorrelationIdFilter` + `RequestIdHolder`** (alias `correlationid`, registered in `globals.before` and `globals.after`). Reuses a well-formed incoming `X-Request-ID` (8–128 chars, ASCII) or generates a UUID v4. A Monolog processor tags every log record with `extra.request_id`, enabling trivial admin↔api log joins in any aggregator.
- **`MaintenanceFilter`** (alias `maintenance`, `globals.before`). `MAINTENANCE_MODE=true` returns `503 Service Unavailable` with `Retry-After`. Bypasses `/health`, `/ping`, `/ready`, `/live`. Custom message via `MAINTENANCE_MESSAGE`, retry seconds via `MAINTENANCE_RETRY_AFTER`.
- **`.github/workflows/release.yml`** — on `v*.*.*` tag push, extracts the matching `## [VERSION]` section from `CHANGELOG.md` via inline awk and creates a GitHub Release. Soft-fails on re-tag (edits the existing release).
- **`php spark env:check [--strict]`** — validates required environment variables, secret strength (length, placeholder detection, hex2bin/base64 normalisation) and treats `CORS_ALLOWED_ORIGINS` as required under `CI_ENVIRONMENT=production`. `init.sh` invokes it before migrations; the GitHub Actions CI workflow invokes it before tests.
- **`app.proxyIPs`** parses comma-separated `cidr=header` pairs into `Config\App::$proxyIPs`. Required when the API runs behind a reverse proxy / load balancer so `ThrottleFilter` and audit logs see the real client IP via `X-Forwarded-For`.
- **`apps:bootstrap --create-api-key` / `--api-key-name`** — generates an active API key bound to `applications.id` and emits `API_KEY=apk_...` + `APP_ID=N` lines on stdout (ready for `awk -F=` parsing from orchestrator scripts). Idempotency anchored to "no duplicate active key per application": a second `--create-api-key` against the same code refuses to insert (raw key is unrecoverable), prints `API_KEY_EXISTS=<prefix>...`, exits non-zero.
- **Coverage gate scaffolding** — `scripts/check-coverage.php` parses clover XML and exits non-zero below the supplied threshold (default 70%). New composer aliases `test:coverage` and `coverage:check`. Wired into `ci.yml` as a soft-fail step until a confirmed baseline lets us flip it to a hard gate.
- **Migration `2026-05-04-070829_AddMissingIndexesAuditMay2026`** — idempotent indexes on filterable / sortable columns: `users.status`, `users.email_verified_at`, `users.created_at`, `users.(oauth_provider, oauth_id)`, `files.uploaded_at`, `files.mime_type`, `files.storage_driver`, `api_keys.is_active`, `api_keys.created_at`, `password_resets.expires_at`. Cross-driver index existence check lets the migration safely re-run.

#### Documentation / runbooks
- **`docs/runbooks/`** (all bilingual):
  - `01-rotate-jwt-secret.md` — pre-flight, staging, validation via `env:check --strict`, deployment roll, smoke, rollback, leak-driven post-mortem.
  - `02-failed-migration-recovery.md` — diagnostic table for clean-rerun / manual-cleanup / down-up / manual-repair paths after a failed `php spark migrate`.
  - `03-upgrade-ci4-minor.md` — CI4 framework minor-bump procedure with the common-breakage-and-fix table.
  - `04-incident-token-leak.md` — 5-phase IR runbook (containment / investigation / root-cause / recovery / post-mortem) for JWT or refresh-token leaks.
- **Pagination conventions documentation** (`docs/tech/pagination.md` + `.es.md`) — clarifies `per_page` (paginated index) vs `limit` (top-N cap) semantics.
- **GitHub issue templates** under `.github/ISSUE_TEMPLATE/`: `bug_report.md`, `feature_request.md`. Existing `pull_request_template.md` preserved.

#### Testing
- **`GoogleLoginSoftDeletedUserTest`** — 2 feature tests pinning the contract that a soft-deleted user attempting Google login is reactivated (not blocked).
- **`UserAccountGuard` regression suite** — locks in the contract that `JwtAuthFilter` delegates account-policy enforcement (status, email-verification, OAuth bypass) to a single class.
- **`JwtAuthFilterAccessPolicyBypassTest`** — verifies the bypass list is read from config dynamically.
- **`AssignableRolesServiceTest`** — 7 integration tests covering the actor-permissions-subset contract.

### Changed

- **`init.sh` chains `migrate → db:seed RbacBootstrapSeeder → swagger:generate → users:bootstrap-superadmin`**. The seeder must run before `bootstrap-superadmin` because the command now attaches a role via `user_roles` (the legacy `users.role` column no longer exists).
- **File access control**: admin override now checks the `files.read` permission; `FileService::findById` enforces ownership at the data layer.
- **Authentication flows** (`login`, `refresh`, OAuth) no longer carry `role` through the pipeline.
- **CRUD scaffolding migrated** to the external `dcardenasl/ci4-api-scaffolding` package. `vendor/bin/make-crud.sh` is the entry point and emits `permission:iam.admin-access` for the protected route group of generated modules.
- **IAM migration timestamps** realigned with the project's chronological migration order.
- **`AuditService`** un-promotes its nullable injected dependencies (`auditWriter`, `auditConfig`, `payloadSanitizer`, `labels`) and shadows them with non-nullable typed properties — same runtime behaviour, no more `?->`, PHPStan can narrow.
- **`scripts/setup.sh`** wraps `migrate`, `db:seed`, and `swagger:generate` with hard timeouts (`run_with_timeout`, configurable via `CI4_MIGRATE_TIMEOUT`, `CI4_SEED_TIMEOUT`, `CI4_SWAGGER_TIMEOUT`). Falls back to no timeout on macOS hosts without coreutils, with a warning.
- **`symfony/mailer`** and remaining transitive dependencies bumped to latest patch/minor.

### Fixed

- **`FileService::findById`** enforces the ownership check that was previously only applied at the index layer.
- **Scaffolding rollback** now restores pre-existing files instead of deleting them when generation aborts.
- **Test suite aligned** with permission-based authorization (no remaining references to the removed `role` column or `RoleAuthorizationFilter`).

### Removed

- **`users.role` column** and all related code paths (DTO fields, model casts, seeders, JWT claim, security context property, login/refresh/OAuth response field).
- **`RoleAuthorizationFilter`** and the `userroleguard` filter alias.
- **`UserRoleGuard`** indirection.
- **`AuditFacetsRequestDTO`** (unused).
- **In-tree scaffolding commands** (`MakeCrud`, `MakeCrudRemove`, `ModuleCheck`) — now provided by `dcardenasl/ci4-api-scaffolding`.
- **Legacy IAM REST endpoints** under `/api/v1/iam/memberships` and `/api/v1/users/{id}/memberships`. Role assignment is performed through the `Users` resource (`role_ids[]` payload).

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
