# Changelog

All notable changes to ci4-api-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **API-007 · `apps:bootstrap --create-api-key`** (2026-05-07) — extends the existing `apps:bootstrap` command with two flags:
  - `--create-api-key` generates an active API key bound to `applications.id` and emits `API_KEY=apk_...` + `APP_ID=N` lines on stdout, ready for `awk -F=` parsing from orchestrator scripts (kickstart consumes this in KICK-001).
  - `--api-key-name="..."` overrides the default key name (`<code>-app-key`).
  - **Idempotency** is anchored to "no duplicate active key per application": a second `--create-api-key` call against the same code refuses to insert (raw key is unrecoverable, so we don't pretend to re-issue it), prints `API_KEY_EXISTS=<prefix>...`, exits non-zero. Application + permission rows remain idempotent (reused on re-run).
  - Reuses `ApiKeyMaterialService` for raw-key generation + SHA-256 hashing. Direct `$db->table()->insert()` (consistent with the existing `BootstrapApplication` pattern of bypassing the model layer for setup-time writes).
  - 4 integration tests in `tests/Integration/Commands/BootstrapApplicationTest.php` covering: bound `application_id`, custom name override, no-flag baseline, and the duplicate-key refusal.
- **Documentación / Gobernanza — Bloque B11** (2026-05-07):
  - **ADR-011 — Multi-tenancy out-of-scope for v1.x** (audit B11.1, EN+ES) — formalizes that the kit is single-tenant, lists the surface that would have to change for a fork to add tenancy, and explicitly rejects retrofitted half-tenancy. Closes audit finding F31.
  - **ADR-012 — Config values resolve at boot, not runtime** (audit B11.1, EN+ES) — pins the `Config\Api` constructor pattern as the contract: env values are read once at boot and treated as immutable for the request lifetime. `getenv()` vs `env()` is documented as a CLI/HTTP bootstrap-order detail. Closes audit finding F32.
  - **`docs/runbooks/`** (audit B11.2, all bilingual):
    - `01-rotate-jwt-secret.md` — pre-flight, staging, validation via `env:check --strict`, deployment roll, smoke, rollback, leak-driven post-mortem.
    - `02-failed-migration-recovery.md` — diagnostic table for clean-rerun / manual-cleanup / down-up / manual-repair paths after a failed `php spark migrate`.
    - `03-upgrade-ci4-minor.md` — CI4 framework minor-bump procedure with the common-breakage-and-fix table.
    - `04-incident-token-leak.md` — 5-phase IR runbook (containment / investigation / root-cause / recovery / post-mortem) for JWT or refresh-token leaks. Includes SQL snippets for revoking by `jti`, blanket-revoking by `user_id`, and capturing the audit trail.
  - **GitHub issue templates** (audit B11.6) — `bug_report.md` and `feature_request.md` under `.github/ISSUE_TEMPLATE/`. Existing `pull_request_template.md` preserved.

### Added
- **Operacional / Deploy — Bloque B10** (2026-05-07):
  - **`CorrelationIdFilter` + `RequestIdHolder`** (audit B10.1) — alias `correlationid`, registered in `globals.before` AND `globals.after`. `before()` reuses a well-formed incoming `X-Request-ID` (8-128 chars, ASCII alphabet) or generates a UUID v4; the chosen value is stamped on `RequestIdHolder` and on the request object. `after()` echoes it as the response header. A Monolog processor in `MonologHandler` reads the holder and tags every log record with `extra.request_id`, so admin↔api log joins are trivial in any aggregator. 7 unit tests in `tests/Unit/Filters/CorrelationIdFilterTest.php`.
  - **`MaintenanceFilter`** (audit B10.4) — alias `maintenance`, wired in `globals.before` (runs before correlationid / locale / cors / JWT). `MAINTENANCE_MODE=true` returns `503 Service Unavailable` with `Retry-After` header and a JSON `ApiResponse::error()` body. Bypasses `/health`, `/ping`, `/ready`, `/live` so orchestrators keep probing. Custom message via `MAINTENANCE_MESSAGE`, retry seconds via `MAINTENANCE_RETRY_AFTER`.
  - **`.github/workflows/release.yml`** (audit B10.5) — on `v*.*.*` tag push, extracts the matching `## [VERSION]` section from `CHANGELOG.md` via inline awk and creates a GitHub Release with those notes. Soft-fails when the release already exists (re-tag scenario) by editing instead of failing.
- **`GoogleLoginSoftDeletedUserTest`** (audit B9.2, 2026-05-07) — 2 feature tests in `tests/Feature/Controllers/Auth/` pinning the contract that a soft-deleted user attempting Google login is **reactivated** (not blocked): `deleted_at` clears, status reverts to `pending_approval`/`active` per env, and the row count at the email stays at 1 (regression guard against accidentally turning the lookup into a hard `findByEmail`). Required resetting the full DI chain (`googleIdentityService` → `googleLoginAction` → `googleAuthHandler` → `authService`) in `setUp`/`tearDown` to avoid stale-mock pollution between tests.
- **Coverage gate scaffolding** (audit B9.4, 2026-05-07) — `scripts/check-coverage.php` parses clover XML and exits non-zero when line coverage is below the supplied threshold (default 70%). New composer aliases `test:coverage` (runs phpunit with coverage enabled) and `coverage:check` (runs the parser). `phpunit.xml` now emits clover at `tests/coverage/clover.xml`. Wired into `.github/workflows/ci.yml` as a soft-fail step (`continue-on-error: true`) until a confirmed baseline lets us flip it to a hard gate.
- **Pagination conventions documentation** (audit B7.5, 2026-05-06) — `docs/tech/pagination.md` (+ `.es.md`) clarifies the `per_page` (paginated index) vs `limit` (top-N cap) distinction. The audit originally flagged `SlowRequestsQueryRequestDTO::limit` as an inconsistency; after review, the parameter is semantically correct (top-N has no concept of page 2) — the doc captures the convention so future endpoints don't pick the wrong one. Added an explanatory comment on `SlowRequestsQueryRequestDTO`. No code changes; `BaseIndexRequestDTO` factor-out tracked as future work.
- **RFC 7807 Problem Details builders** (audit B7.4 / **ADR-010**, 2026-05-06) — additive helpers in `App\Libraries\ApiResponse`:
  - `problemDetails($errors, $title, $status, $type, $instance, $detail)` — pure 7807 body builder. Defaults `type` to `"about:blank"` per RFC 7807 §4.2; preserves the per-field `errors` map as a 7807 extension member.
  - `negotiateError($acceptHeader, ...)` — content-negotiation entry point. Returns `{body, content_type}`; flips to 7807 when `application/problem+json` appears in Accept, otherwise stays on the legacy envelope.
  - `clientPrefersProblemJson($accept)` — minimal q-aware Accept parser.
  Default error envelope preserved untouched for back-compat — controllers opt in deliberately. 6 new unit tests in `tests/Unit/Libraries/ApiResponseTest.php` (21 total). ADR-010 (EN+ES) documents the decision, parser limits, and future work.
- **`Idempotency-Key` opt-in support** (audit B7.3 / **ADR-009**, 2026-05-06) — RFC-style retry safety for state-changing endpoints:
  - **Migration** `2026-05-06-100000_CreateIdempotencyKeysTable` — PK on `idempotency_key`, indexes on `expires_at` (cleanup) and `(actor_id, endpoint)` (lookup).
  - **`IdempotencyFilter`** registered as alias `idempotency`, applied per-route (NOT in globals). Behavior matrix: 400 on malformed key, replay with `Idempotent-Replay: true` on cache hit + matching body hash, `409 Conflict` with `Idempotency-Mismatch: true` on hit + body mismatch, persist only on 2xx responses, ignore non-{POST/PUT/PATCH/DELETE}, ignore missing header. Body hashed with SHA-256.
  - **In-flight state** between `before()` and `after()` held in `private static ?array $pending` (CI4 instantiates filter fresh per phase; PHP-FPM single-request keeps static safe).
  - **Race-safe persistence:** duplicate-key error from concurrent insert silently swallowed.
  - **`IdempotencyFilter::flushPending()`** test helper for resetting state between assertions.
  - 6 feature tests covering the full matrix in `tests/Feature/Filters/IdempotencyFilterTest.php`. ADR-009 (EN+ES) documents the contract and future work (cleanup job, per-route TTL, Octane considerations).
- **`AssignableRolesService`** (audit B7.1, 2026-05-06) — extracted the anti-escalation logic from `UserController::assignableRoles()` into a dedicated `App\Services\Iam\AssignableRolesService`. The controller method shrinks from 39 lines of raw queries + filtering to a 5-line delegation through `handleRequest()`. Wired in `IamDomainServices::assignableRolesService()`. 7 integration tests in `tests/Integration/Services/Iam/AssignableRolesServiceTest.php` pin the contract: a role is assignable iff every permission code attached to that role is already in the actor's effective set (subset check via `array_diff`).
- **API versioning policy** (audit B7.2 / **ADR-008**, 2026-05-06):
  - **`Config\Api::$apiVersions`** — array map keyed by version (`v1`, `v2`, ...) with `status` / `deprecated_at` / `sunset_at` / `successor` per entry. Single source of truth for the version lifecycle.
  - **`DeprecationHeadersFilter`** — alias `deprecationheaders`, runs in `globals.after`. Emits `Deprecation` (IETF draft / RFC 8594 family), `Sunset` (RFC 8594), and `Link: rel="successor-version"` (RFC 5988) on responses for non-current versions. 6 unit tests in `tests/Unit/Filters/DeprecationHeadersFilterTest.php`.
  - **`GET /api/versions`** — public, unauthenticated meta-endpoint (no version prefix) returning `{current, versions: [...]}`. Suitable for CI/CD polling and compatibility-matrix tooling. 2 feature tests in `tests/Feature/Controllers/ApiVersionsEndpointTest.php`.
  - **`docs/adr/ADR-008-API-VERSIONING-AND-DEPRECATION.md`** (+ `.es.md`) — captures the lifecycle SLA defaults (18-month active support, 6-month deprecation notice, sunset returns 410), the "no breaking changes within v1" rule, and pointers for future v2 planning.

### Changed
- **IAM schema simplified**: the `app_user_memberships` + `membership_roles` pair was collapsed into a single `user_roles(user_id, role_id, assigned_at, assigned_by_user_id)` join table. Roles are now global (the `roles.application_id` column was dropped); per-application scoping is preserved via `permissions.application_id`. See migrations `2026-05-03-100003_CreateUserRolesTable` through `2026-05-03-100007_DropMembershipsTables`. Migration `100004` (the data backfill) runs inside a transaction so a partial failure does not leave `user_roles` half-populated.
- **`users:bootstrap-superadmin`** now attaches the `superadmin` role via a `user_roles` row (was: `app_user_memberships` row).
- **`EffectivePermissionsResolver`** now derives codes from `user_roles → roles → role_permissions → permissions`.
- **`JwtAuthFilter` bypass list** moved from a hardcoded array in the filter to `Config\Api::$accessPolicyBypassRoutes`. The list still defaults to `['api/v1/auth/resend-verification']` — the only route that legitimately needs to skip account-policy checks. Adding a new bypass now requires editing config (reviewable) instead of the filter source.
- **`Config\Cors`** refuses to boot in `CI_ENVIRONMENT=production` when both `CORS_ALLOWED_ORIGINS` and `app.baseURL` are empty — earlier this configuration silently produced an empty origin list and a confusing 4xx storm.
- **`AuditService`** un-promotes its nullable injected dependencies (`auditWriter`, `auditConfig`, `payloadSanitizer`, `labels`) and shadows them with non-nullable typed properties — same runtime behavior, no more `?->` everywhere, PHPStan can narrow.
- **`scripts/setup.sh`** wraps `migrate`, `db:seed`, and `swagger:generate` with hard timeouts (`run_with_timeout`, configurable via `CI4_MIGRATE_TIMEOUT`, `CI4_SEED_TIMEOUT`, `CI4_SWAGGER_TIMEOUT`). Falls back to no timeout on macOS hosts without coreutils, with a warning.
- **PHPStan** raised from level 7 to **level 8** with a `phpstan-baseline.neon` capturing 125 historical type-debt errors. New code must pass cleanly; baseline shrinks incrementally.

### Added
- `php spark env:check [--strict]` — validates required environment variables, secret strength (length, placeholder detection, hex2bin/base64 normalization), and treats `CORS_ALLOWED_ORIGINS` as required in production. `init.sh` invokes it before running migrations; the GitHub Actions CI workflow invokes it before tests.
- New env var `app.proxyIPs` parses comma-separated `cidr=header` pairs into `Config\App::$proxyIPs`. Required when the API runs behind a reverse proxy / load balancer (ALB, nginx, Cloudflare) so `ThrottleFilter` and audit logs see the real client IP via `X-Forwarded-For`. Without it, every request appears to come from the proxy itself.
- `UserAccountGuard` regression suite (`tests/Unit/Services/Users/UserAccountGuardTest.php`) — locks in the contract that `JwtAuthFilter` delegates account-policy enforcement (status, email-verification, OAuth bypass) to a single class.
- `JwtAuthFilterAccessPolicyBypassTest` — verifies the bypass list is read from config dynamically, including support for entries with leading slashes.
- Migration `2026-05-04-070829_AddMissingIndexesAuditMay2026` — idempotent indexes on filterable / sortable columns: `users.status`, `users.email_verified_at`, `users.created_at`, `users.(oauth_provider, oauth_id)`, `files.uploaded_at`, `files.mime_type`, `files.storage_driver`, `api_keys.is_active`, `api_keys.created_at`, `password_resets.expires_at`. Cross-driver index existence check lets the migration safely re-run.

### Removed
- IAM REST endpoints under `/api/v1/iam/memberships` and `/api/v1/users/{id}/memberships`. Role assignment to users is performed through the existing `Users` resource (`role_ids[]` in the payload).

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
