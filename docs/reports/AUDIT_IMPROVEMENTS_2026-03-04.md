Audit improvements completed on March 4, 2026

## Context
Following the auditing plan, the template now enforces stricter boundaries between transport-layer helpers, services, and persistence. This report highlights the refactors that reduced technical debt and documents the test signal included with the work.

## Key Changes
- `ApiController` now delegates input merging to `App\Support\RequestDataCollector`, which is registered in `Config\Services` so callers do not duplicate the logic (`app/Controllers/ApiController.php`, `app/Support/RequestDataCollector.php`, `app/Config/Services.php`).
- The file module now exposes only `destroy(int, ?SecurityContext)` so controllers/services share a single contract (`app/Services/Files/FileService.php`, `app/Interfaces/Files/FileServiceInterface.php`, `app/Controllers/Api/V1/Files/FileController.php`, tests updated in `tests/Unit/Services/FileServiceTest.php`).
- Base repositories and repositories interfaces shed mutable `where`/`orderBy` helpers to keep builders stateless and reduce leakage between calls (`app/Repositories/BaseRepository.php`, `app/Interfaces/Core/RepositoryInterface.php`).
- Audit sanitization now filters a broader set of tokens/secrets plus key patterns, and `UserEntity::toArray()` explicitly removes sensitive attributes before audit `toArray` calls (`app/Services/System/AuditPayloadSanitizer.php`, `tests/Unit/Services/System/AuditPayloadSanitizerTest.php`, `app/Entities/UserEntity.php`, `tests/Unit/Entities/UserEntityTest.php`).
- DTO/audit boundaries now respect DI: `BaseRequestDTO` uses `service('validation')`, `Auditable` stores an injected `AuditServiceInterface`, each auditable model wires the service via `Services::auditService()`, and a regression test ensures these helpers never call `\Config\Services` statically (`app/DTO/Request/BaseRequestDTO.php`, `app/Traits/Auditable.php`, models under `app/Models/*`, new test `tests/Unit/Architecture/BoundaryStaticFacadeConventionsTest.php`).
- Added structural helpers/tests (`RequestDataCollectorTest`, `BoundaryStaticFacadeConventionsTest`, updated architecture guard tests) to keep the new contracts under cover.

## Testing
- `php spark tests:prepare-db && vendor/bin/phpunit --colors=never`
  - Result: 541 tests / 1212 assertions run; 2 tests skipped; only warning is “No code coverage driver available”.

