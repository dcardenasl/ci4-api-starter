# Changelog

All notable changes to ci4-api-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.6.4] — 2026-06-10

### Changed

- **`dcardenasl/ci4-api-scaffolding`** — updated constraint from `^0.7.5` to `^1.0` following the stable v1.0.0 release of the scaffolding package.

## [2.6.3] — 2026-06-06

### Changed

- **`dcardenasl/ci4-api-scaffolding`** — updated lock from v0.7.7 to v0.7.8, which restores PascalCase test directory output (`tests/Unit`, `tests/Integration`, `tests/Feature`) for generated CRUD modules, so scaffolded test classes are discovered on case-sensitive filesystems.

## [2.6.2] — 2026-06-04

### Fixed

- **`i18n-check.php`** — `require` calls for language files are now wrapped in `try-catch (\Throwable)` so PHP parse errors are reported cleanly as check failures instead of causing a fatal crash.

### Changed

- **`dcardenasl/ci4-api-core` constraint** — bumped from `^0.9.0` to `^1.0`; `dcardenasl/ci4-api-scaffolding` bumped from `^0.7.x` to `^0.7.7` following the stable v1.0.0 release of the core package.

## [2.6.1] — 2026-06-01

### Changed

- **`init.sh` and `install.sh` logging control** — enhanced with `CI4_FORCE_LOG_TO_FILE` conditional flag support for consistent log handling across hub, domain, BFF, and admin subprocesses in containerized/CI environments.

## [2.6.0] — 2026-05-31

### Added

- **`POST /api/v1/iam/self-permissions`** — domain apps can register their own permissions using only an `X-App-Key`, without a superadmin JWT. Each code must be namespaced to the calling app's code (e.g. `catalog.*` for an app registered as `catalog`); out-of-namespace codes are rejected and counted separately. Idempotent: already-registered codes are reported as `existing` and skipped. Route is filtered by `appKeyRequired` + `throttle`. Implemented in `SelfPermissionsController` and `SelfPermissionService`.
- **`RbacBootstrapSeeder::ensureDomainApplications()`** — re-creates domain app rows in the `applications` table from `Config\DomainAppsRegistry` when the class exists, so a `php spark migrate:fresh && php spark db:seed RbacBootstrapSeeder` cycle does not permanently lose domain app registrations.

## [2.5.2] — 2026-05-30

### Changed

- **`dcardenasl/ci4-api-core` bumped to `v0.9.2`** — lockfile updated to commit `7c64e19`.
- **Audit doc** (`docs/audits/make-crud-audit.md`) — replaced project-specific container name `teatromuseo_mysql` with the canonical `mysql` reference.

## [2.5.1] — 2026-05-29

### Fixed

- **`AppExceptionHandler`** — restored `extends BaseExceptionHandler` which was inadvertently removed; the class now correctly subclasses `\dcardenasl\Ci4ApiCore\Exceptions\BaseExceptionHandler`, enabling CI4's exception handler wiring to function as intended.

## [2.5.0] — 2026-05-29

### Added

- **`AppExceptionHandler`** (`app/Libraries/Exceptions/`) — app-level exception handler extending `BaseExceptionHandler` from `ci4-api-core`; wired into CI4 via `Config\Exceptions::handler()`.
- **Health route delegation** — `GET /health` in `Routes/v1/system.php` now delegates to `\dcardenasl\Ci4ApiCore\Http\HealthCheckController::index`.

## [2.4.0] — 2026-05-29

### Added

- **Dynamic Test Suites:** Implemented dynamic test discovery and execution patterns (`F9`) to ensure feature-test coverage scales with scaffolded domain modules.

### Changed

- **Platform Coherence:**
  - `dcardenasl/ci4-api-core` bumped to `^0.9.0`.
  - `dcardenasl/ci4-api-scaffolding` bumped to `^0.7.0`.
  - Enforced strict CS-Fixer rules and implemented PHPStan bootstrap hardening (`F6`) to maintain Level 8 compliance.
- **Dependencies:** Updated to align with current CI4 ecosystem requirements.

## [2.3.0] — 2026-05-27

### Added

- **Automatic `app_id` resolution in `PermissionService`** — when creating a permission, `application_id` is now auto-populated from `SecurityContext::$app_id` if not supplied explicitly. Eliminates the need to pass `application_id` from call sites when the API key already encodes the application.

### Changed

- **Local `RateLimitResponseHelpers` removed** — `app/Filters/Concerns/RateLimitResponseHelpers.php` deleted; `AuthThrottleFilter` and `ThrottleFilter` now import `dcardenasl\Ci4ApiCore\Http\Filters\Concerns\RateLimitResponseHelpers` from `ci4-api-core`. No behavioural changes.
- **Repository and service generics aligned with `ci4-api-core ^0.8.0`** — all starter repositories and services now declare the generic type parameters introduced in the core library (`BaseRepository<T>`, `BaseCrudService<T>`, etc.). PHPStan level-8 picks up the improvement automatically.
- **Auth DTO contracts tightened** — `IntrospectResponseDTO` updated to carry `app_id`; `LoginResponseDTO` refined.
- **IAM services and models adopt `BaseCrudService` + `BaseAuditableModel` generics**.
- **PHPStan baseline reduced** — 132 obsolete baseline entries removed after the generic typing upgrade.
- **`dcardenasl/ci4-api-core` bumped to `^0.8.0`**; `dcardenasl/ci4-api-scaffolding` bumped to `^0.6.0`.
- **CodeIgniter 4 updated to v4.7.3**.

### Fixed

- **`users:bootstrap-superadmin` is now idempotent** — returns `EXIT_SUCCESS` (was `EXIT_ERROR`) when a superadmin already exists. `init.sh` and automated CI pipelines that call the command unconditionally no longer fail on a pre-seeded database.
- **Health endpoint degrades on isolated disk pressure** — when async audit logging is enabled and the only critical check is disk I/O, the `GET /health` endpoint now returns `degraded` instead of `unhealthy`. Orchestrators no longer restart a hub that is still serving traffic.

## [2.2.2] — 2026-05-23

### Fixed

- `app/Config/Format.php` now defines `jsonEncodeDepth = 512` so API responses format correctly under CI 4.7.3+.
- `app/Libraries/Files/Base64Processor.php` now validates payload size before decoding to avoid memory spikes on large base64 uploads; the `ApiKeyService` fake repository test was updated to match the current `RepositoryInterface`.

## [2.2.1] — 2026-05-23

### Fixed

- `scripts/bootstrap_env.php` now accepts commented placeholders (`; key = value` / `# key = value`) when updating `.env` files.
- `scripts/setup.sh` preserves the `repositories` array shape after stripping local `ci4-api-*` path repositories, preventing malformed `composer.json` output when the array becomes sparse.

## [2.2.0] — 2026-05-22

### Fixed

- Pre-commit hook now regenerates and auto-stages `public/swagger.json` before running CS-Fixer and PHPStan. Manual `php spark swagger:generate && git add public/swagger.json` steps before commits are no longer needed.
- `composer arch-drift` now runs `@test:prepare-db` as its first step, preventing a "Tests database is empty" failure on first run after `git clone` or DB reset.

### Improved

- `ServiceModelDependencyConventionsTest` docblock documents the canonical cross-entity query pattern: inject a second repository via the constructor and use `findBy()` instead of `model(\App\Models\...::class)` inline FQCNs.

### Dependencies

- Updated `dcardenasl/ci4-api-core` to `^0.7.0` (adds `RepositoryInterface::findBy()`).
- Updated `dcardenasl/ci4-api-scaffolding` to `^0.5.0`.

## [2.1.1] — 2026-05-21

Fixes the encryption key not being written during `setup.sh` initialization when `.env.example` contains a quoted empty value for `encryption.key`.

### Fixed

- **`setup.sh` encryption key generation** — `php spark key:generate --force` was silently skipping the write when `encryption.key` had a quoted empty placeholder (`encryption.key = ""`), because CI4's key command uses a regex that requires an unquoted value. The fix generates the key inline (`hex2bin:$(php -r '…')`) and injects it through `bootstrap_env.php`, which handles any existing value format.

## [2.1.0] — 2026-05-19

Adds a complete trash lifecycle to the files API (soft delete, restore, force delete, bulk variants) so the admin's existing trash UI is no longer hitting 404s. Adds image-variant generation on upload and avatar-as-file-reference tracking. Fixes the throttle fixed-window TTL reset bug. Documentation pass on validation, scaffolding package references, and the file-storage guide. This release also bumps the `codeigniter4/framework` constraint to `^4.7` to match the current stable environment.

### Changed

- **`codeigniter4/framework` constraint bumped from `^4.5` to `^4.7`** — locks to the current stable CI4 (v4.7.2). The effective floor was already 4.6 because `dcardenasl/ci4-api-core` requires it; the constraint now reflects what is actually exercised in CI and lockfile.
- **`dcardenasl/ci4-api-core` bumped to `^0.6.0`** — picks up `AbstractServiceClient` and the outbound HTTP config knobs introduced in core v0.5.0; v0.6.0 widens the core's own CI4 requirement to `^4.7`, matching the constraint above. Purely additive on the hub side; no behaviour change.

### Docs

- **README:** `bin/make-crud.sh` references replaced with `vendor/bin/make-crud.sh`. The local `bin/` directory was removed when the scaffolding engine was extracted to `dcardenasl/ci4-api-scaffolding` in v2.0.0; the binary now ships from the dev dependency under `vendor/bin/`.

Adds a complete trash lifecycle to the files API (soft delete, restore, force delete, bulk variants) so the admin's existing trash UI is no longer hitting 404s. Documentation pass on validation, scaffolding package references, and the file-storage guide.

### Added

- **Files soft-delete + trash lifecycle.**
  - Migration `2026-05-17-045115_AddSoftDeleteToFilesTable` adds `files.deleted_at DATETIME NULL` + `files.deleted_by_user_id INT UNSIGNED NULL` (no FK — SQLite, used by the test harness, does not support adding FKs to existing tables; integrity is enforced at the service layer) plus an index on `deleted_at` (MySQL only).
  - `FileModel::$useSoftDeletes = true`. `deleted_at` is filterable and sortable.
  - `FileIndexRequestDTO` accepts `trashed=without|only|with` (default `without`).
  - `FileService` gains `restore()`, `forceDestroy()`, `bulkDestroy()`, `bulkRestore()`, `bulkForceDestroy()`. Bulk variants return per-item outcomes `{id, ok, error?}` so partial successes are reportable.
  - `FileRepository` gains `findIncludingTrashed()` and `purge()`; `restore()` clears both `deleted_at` and `deleted_by_user_id`.
- **5 new HTTP endpoints**, gated by `jwtauth` + `throttle`:
  - `POST /api/v1/files/{id}/restore` — un-trash a file.
  - `DELETE /api/v1/files/{id}/force` — permanently delete (storage + DB row). Refuses with `400` if the file is not currently trashed.
  - `POST /api/v1/files/bulk-delete` — bulk soft-delete.
  - `POST /api/v1/files/bulk-restore` — bulk restore.
  - `POST /api/v1/files/bulk-force-delete` — bulk permanent delete.
- **OpenAPI documentation** for the 6 new paths (5 endpoints + new `trashed` query param on `GET /files`).
- **Authorization audit codes** `unauthorized_file_restore` and `unauthorized_file_force_delete` for the new lifecycle paths.
- **Image variant generation on upload** (`app/Libraries/Files/ImageVariantProcessor.php`).
  - When an uploaded file is an image, `FileService` now calls `ImageVariantProcessor::process()` to generate resized variants (e.g. `thumb`, `medium`) and persists them as `FileReference` rows via the new `FileReferenceRepository`.
  - Migration `2026-05-19-100000_CreateFileReferencesTable` adds `file_references(id, file_id, variant, path, storage_disk, width, height, size, created_at)`.
  - `FileResponseDTO` exposes a `variants` array so consumers can link directly to derivative sizes without re-fetching.
  - New interfaces: `FileReferenceRepositoryInterface`, extended `FileRepositoryInterface` and `FileServiceInterface`.
- **Avatar stored as a `FileReference` on profile update.** `UpdateSelfProfileAction` and `UpdateUserAction` now register a `FileReference` row pointing at the uploaded avatar so it appears in the file-usage graph and is subject to the same storage lifecycle rules as any other file. No change to the `avatar_url` field or the response contract.

### Fixed

- **`ThrottleFilter` / `ApiKeyThrottleHelpers` fixed-window TTL reset.** The previous implementation called `Cache::set()` on the counter key each request, which reset the TTL window on every hit. A bad actor spacing requests to stay just under the limit could sustain the rate indefinitely. Replaced with `Cache::increment()` so the window TTL is set once when the key is first created and preserved for all subsequent increments within the window.

### Changed

- **`DELETE /api/v1/files/{id}` is now a soft delete.** Sets `deleted_at` and `deleted_by_user_id` and keeps the storage object on disk. Use `DELETE /api/v1/files/{id}/force` after to purge permanently. The HTTP contract is unchanged (still `200` on success); a second `DELETE` on an already-trashed file returns `404` because the file is invisible to default queries, which matches REST semantics.
- **`docs/architecture/VALIDATION.md`** (and `.es.md`) rewritten end-to-end. The previous text described `InputValidationService` (removed in v2.0.0) and a `validateOrFail($data, 'auth', 'register')` global helper that does not exist. Current contents document the actual `BaseRequestDTO::rules()` + `map()` pattern, custom messages via `lang()`, the `ValidationException` i18n architecture test, and an explicit "what this project does NOT use" section so stale refs don't confuse readers.
- **`docs/tech/file-storage.md`** gains a "Soft Delete & Trash" section: lifecycle table, `trashed` filter semantics, bulk response shape, authorization model, and the `InvalidChars` filter gotcha.
- **`CLAUDE.md`** and `docs/template/{ARCHITECTURE_CONTRACT,CRUD_FROM_ZERO}.md` updated to reference `dcardenasl/ci4-api-scaffolding` instead of the renamed `ci4-api-crud-maker`, and the pointer files now use the GitHub URL since Composer's dist tarball strips `vendor/dcardenasl/*/docs/`.

### Notes

- **CI4 `InvalidChars` global filter and JSON integers.** `mb_check_encoding()` throws `TypeError` when it recurses into a JSON body containing raw integers. The new bulk endpoints expect `ids` as strings; `FileBulkActionRequestDTO` casts back to `int` internally. Documented in `docs/tech/file-storage.md` and tracked as `SEÑAL-API-001` in `TASKS.md`. The matching workaround on the admin side ships in `ci4-admin-starter@v2.0.0`.

### Internal

- **`FileServiceTest`**: `testDestroyOwnFileReturnsSuccess` replaced by `testDestroyOwnFileSoftDeletesAndPreservesStorage`; 4 new tests cover `forceDestroy()` (purge and refusal paths) and `restore()` (clear and refusal paths). Suite now 26 tests / 56 assertions in this file alone.
- **`FileControllerTest`** gains 9 feature tests covering the full lifecycle and the `?trashed=only` listing.
- **`AuthThrottleFilterTest` / `ThrottleFilterTest`** updated for the `Cache::increment()` semantics; 4 new assertions verify TTL preservation across multiple requests.
- **`php-cs-fixer` bumped to `^3.95`** in dev dependencies.
- **Full suite**: PHPStan level 8 clean. `composer quality` passes end-to-end.

## [2.0.0] — 2026-05-15

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
- **IAM REST endpoints** under `/api/v1/iam/` (all gated by `permission:iam.admin-access`): `roles` CRUD + `roles/{id}/permissions`, `permissions` CRUD, `users/{user_id}/permissions?app=<code>` (effective permissions scoped to an application — `UserPermissionsController` resolves the application by code, returns `{user_id, application:{id,code,name}, permissions:string[]}`). Role assignment to users is performed via the existing `Users` resource (`role_ids[]` payload).
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
- **Single-command Docker bootstrap** — `docker compose up -d` now works without copying any env file. An idempotent `docker/entrypoint.sh` (runs as root, then drops to www-data via Apache) seeds `.env` from `.env.example`, generates `JWT_SECRET_KEY` and `encryption.key` on first boot, runs `php spark migrate` and `php spark db:seed RbacBootstrapSeeder`. Secrets persist across `docker compose down` in the named volume `ci4-api-env`; `down -v` resets. `docker-compose.yml` attaches to the shared external bridge network `ci4-platform`, lets host ports be overridden via `API_HOST_PORT` / `DB_HOST_PORT` / `PHPMYADMIN_HOST_PORT`, and ships phpMyAdmin under the `tools` profile (`docker compose --profile tools up -d phpmyadmin`).

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
- **`UserPermissionsEndpointTest`** + **`UserPermissionsServiceTest`** — feature and unit coverage for the `GET /iam/users/{id}/permissions?app=<code>` endpoint (happy path, missing `app` parameter, unknown application code, unknown user, authorization gate).
- **CI matrix expanded** in `.github/workflows/ci.yml`:
  - Primary test job now runs PHP `8.2` / `8.3` / `8.4` with `fail-fast: false`.
  - New `ci4-compatibility` job matrix-pins `codeigniter4/framework` to `4.6.*` and `4.7.*` against PHP `8.2` / `8.3`, executing the Unit suite (DB-less) so regressions in the framework floor and ceiling surface in PRs.

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

[unreleased]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.5.0...HEAD
[2.5.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.4.0...v2.5.0
[2.4.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.2.2...v2.3.0
[2.2.2]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.2.1...v2.2.2
[2.2.1]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/dcardenasl/ci4-api-starter/compare/v2.1.1...v2.2.0
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
