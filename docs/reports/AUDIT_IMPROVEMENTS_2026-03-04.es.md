Mejoras de auditoria completadas el 4 de marzo de 2026

## Contexto
Siguiendo el plan de auditoria, el template ahora aplica limites mas estrictos entre helpers de transporte, servicios y persistencia. Este reporte resume los refactors que redujeron deuda tecnica y documenta la señal de pruebas incluida con el trabajo.

## Cambios Clave
- `ApiController` ahora delega el merge de input a `App\Support\RequestDataCollector`, registrado en `Config\Services` para evitar logica duplicada en consumidores (`app/Controllers/ApiController.php`, `app/Support/RequestDataCollector.php`, `app/Config/Services.php`).
- El modulo de archivos ahora expone solo `destroy(int, ?SecurityContext)` para que controladores y servicios compartan un contrato unico (`app/Services/Files/FileService.php`, `app/Interfaces/Files/FileServiceInterface.php`, `app/Controllers/Api/V1/Files/FileController.php`, tests en `tests/Unit/Services/FileServiceTest.php`).
- Repositorios base e interfaces de repositorio eliminaron helpers mutables `where`/`orderBy` para mantener builders sin estado y reducir fugas entre llamadas (`app/Repositories/BaseRepository.php`, `app/Interfaces/Core/RepositoryInterface.php`).
- La sanitizacion de auditoria ahora filtra mas tipos de tokens/secrets y patrones de keys, y `UserEntity::toArray()` elimina atributos sensibles de forma explicita antes de llamadas `toArray` usadas por auditoria (`app/Services/System/AuditPayloadSanitizer.php`, `tests/Unit/Services/System/AuditPayloadSanitizerTest.php`, `app/Entities/UserEntity.php`, `tests/Unit/Entities/UserEntityTest.php`).
- Los limites DTO/auditoria ahora respetan DI: `BaseRequestDTO` usa `service('validation')`, `Auditable` almacena un `AuditServiceInterface` inyectado, cada modelo auditable cablea el servicio con `Services::auditService()`, y un test de regresion asegura que estos helpers no llamen `\Config\Services` de forma estatica (`app/DTO/Request/BaseRequestDTO.php`, `app/Traits/Auditable.php`, modelos en `app/Models/*`, test `tests/Unit/Architecture/BoundaryStaticFacadeConventionsTest.php`).
- Se agregaron helpers/tests estructurales (`RequestDataCollectorTest`, `BoundaryStaticFacadeConventionsTest`, y actualizaciones de guardrails de arquitectura) para mantener los nuevos contratos bajo cobertura.

## Pruebas
- `php spark tests:prepare-db && vendor/bin/phpunit --colors=never`
  - Resultado: 541 tests / 1212 assertions; 2 tests skipped; unico warning: “No code coverage driver available”.
