# Repository Guidelines

## Project Structure & Module Organization
- `app/` contains the API implementation (controllers in `app/Controllers/Api`, services in `app/Services`, models in `app/Models`, entities in `app/Entities`, filters in `app/Filters`, and shared helpers in `app/Libraries`).
- `tests/` holds PHPUnit tests split into `Unit/`, `Integration/`, and `Feature/` suites.
- `public/` is the web root; `writable/` stores logs, cache, and uploads.
- `docs/` includes supplementary documentation; generated OpenAPI output is `public/swagger.json`.

## Build, Test, and Development Commands
- `composer install` installs PHP dependencies.
- `php spark serve` runs the dev server at `http://localhost:8080`.
- `php spark migrate` applies database migrations.
- `php spark make:crud {Resource} --domain {Domain} --route {slug}` scaffolds new CRUD resources (default path for new CRUD work).
- `vendor/bin/phpunit` runs the full test suite; add `--testdox` for readable output.
- `composer cs-check` runs PHP-CS-Fixer in dry-run mode; `composer cs-fix` applies fixes.
- `composer phpstan` runs static analysis.
- `php spark swagger:generate` regenerates `public/swagger.json` from `app/Documentation/` annotations.

## Coding Style & Naming Conventions
- Follow PSR-12 with short array syntax; PHP-CS-Fixer enforces this (`.php-cs-fixer.php`).
- Use `App\` namespace with PSR-4 autoloading (`app/`), and `Tests\` for test classes (`tests/`).
- Controllers extend `ApiController`; services implement their interfaces and return `ApiResponse` arrays.
- Prefer descriptive, verb-based test names (e.g., `testLoginWithValidCredentialsReturnsUserData`).

## Agent Critical Rules
- For any new CRUD resource, use `php spark make:crud` first. Do not handcraft the initial CRUD skeleton unless the user explicitly requests manual creation.
- Keep controllers thin: for standard CRUD, define `protected string $serviceName` and delegate to inherited `ApiController` CRUD methods.
- Place OpenAPI docs in `app/Documentation/`; do not add OpenAPI annotations directly in controllers.
- Use `lang()` for user-facing messages and keep parity in `app/Language/en/` and `app/Language/es/`.
- Follow route filters from `app/Config/Routes.php`: auth public endpoints use `authThrottle`; protected endpoints use `jwtauth`; admin writes use `roleauth:admin`.
- Register new services in `app/Config/Services.php` using `{resource}Service` naming.

## Testing Guidelines
- Framework: PHPUnit (`phpunit.xml`).
- `tests/Unit` are fast and do not require a database; `tests/Integration` and `tests/Feature` require the test DB (`ci4_test`).
- Run targeted suites: `vendor/bin/phpunit tests/Unit` or `vendor/bin/phpunit --filter Class::method`.

## Commit & Pull Request Guidelines
- Commit history shows mixed styles (`fix: ...`, `Fix: ...`, or sentence case). Prefer short, imperative messages and keep them scoped (e.g., `fix: adjust pagination response`).
- PRs should include a clear summary, linked issues (if any), and notes on migrations, config changes, or new endpoints. Add screenshots only when API responses or docs output change.

## Security & Configuration Tips
- Copy `.env.example` to `.env` and set `JWT_SECRET_KEY` plus `encryption.key` before running locally.
- Do not commit secrets; keep credentials in `.env` or CI variables.
