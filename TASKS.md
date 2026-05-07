# TASKS — ci4-api-starter

> Fuente de verdad para trabajo en este repo.
> Gestionado desde Cowork/VentureOS. Ejecutado desde Claude Code.
> Última actualización: 2026-05-07

---

## 🔴 En progreso

*(vacío — API-005 y API-006 cerrados; bloque B7 completo. Próximas tareas son B9.2 / B10.1 / B11.1.)*

---

## 🟡 Próximo (ordenado por prioridad)

> Forman parte del milestone "Enterprise hardening B5–B11" activo en `../TASKS.md`.


- **[B9.2]** Feature tests faltantes: `FileUploadTest` (multipart + base64 + invalid MIME), `RbacEscalationTest`, `SoftDeleteOAuthTest`.
- **[B10.1]** `CorrelationIdFilter`: lee `X-Request-ID` o genera UUID; emite en respuesta y en cada log line via Monolog processor.
- **[B11.1]** ADRs faltantes 008–012 (versioning, idempotency, problem-details, multi-tenancy out-of-scope, config runtime mutability).

---

## ⚪ Backlog

- [API-010] `GET /iam/users/{id}/permissions?app=<code>` — permisos de un usuario filtrados por app de dominio (útil para domain apps que quieren verificar permisos sin introspect completo)
- [API-011] Publicar `ci4-api-core` en Packagist (actualmente VCS/path repo) — bloqueado por CORE v1.0
- [API-012] Docker out-of-the-box — Dockerfile + docker-compose para dev (api-starter ya tiene Dockerfile; falta docker-compose unificado api+admin+mysql)
- [API-013] CI/CD pipeline de ejemplo — GitHub Actions (api-starter ya tiene; documentar el patrón para derivados)
- [API-014] Soporte multi-tenant nativo en el modelo de usuarios — fuera de alcance v1.x, ver ADR-011 cuando exista

---

## ✅ Completadas recientes

- **[API-007] `apps:bootstrap --create-api-key`** (2026-05-07) — `BootstrapApplication` ahora acepta `--create-api-key` (flag) y `--api-key-name` (opcional). Tras crear/encontrar la application + permission, genera una API key activa vinculada a `applications.id` reusando `ApiKeyMaterialService`, e imprime stdout parseable: `API_KEY=apk_...` + `APP_ID=N` (delimitadores `--- machine-readable output ---` para grep/awk). Idempotencia: app + permission siguen siendo idempotentes; un segundo `--create-api-key` contra el mismo code rechaza el insert (la raw key original es irrecuperable), imprime `API_KEY_EXISTS=<prefix>` y exit 1. Tests: 4 integration tests en `tests/Integration/Commands/BootstrapApplicationTest.php` (bound application_id, custom name override, no-flag baseline, duplicate-key refusal). Desbloquea KICK-001 en kickstart. Suite 656 verde · PHPStan 8 limpio.
- **[API-006] /auth/introspect re-resuelve scope por X-App-Key** (2026-05-07) — `TokenIntrospectionServiceInterface::introspect()` ahora acepta `?int $applicationId = null`. El servicio inyecta `EffectivePermissionsResolver`; cuando hay `uid > 0` y `applicationId !== null`, re-resuelve scope vía `resolve(uid, applicationId)` (los service tokens sin `uid` mantienen el scope baked-in del JWT). El controller re-lee `X-App-Key` (mismo patrón que `ServiceTokenController`, decoupled del subclass del request) y resuelve `application_id` por hash en `ApiKeyRepository`. Tests: nuevo `testIntrospectReResolvesScopeAgainstCallerApplication` valida que un user JWT con `users.read` (scope `self`) introspeccionado con un X-App-Key atado a `mydomain` retorna `[mydomain.read, mydomain.write]`. Suite Feature 117 verde, Unit+Integration 535 verde, PHPStan 8 limpio, arch-drift verde, swagger.json regenerado. Desbloquea DOM-002.
- **[API-005] JwtAuthFilter null-safe para service tokens** (2026-05-07) — Reemplazado `(int) $decoded->uid` (líneas 99 y 105) por uso de `$userId` ya null-safe (línea 76) más nullable propagado: `$contextUserId = $userId > 0 ? $userId : null`. Service tokens ahora inyectan `uid=null` con scope poblado en `ApiRequest::setAuthContext()` y `SecurityContext` (ambos ya nullable). `PermissionFilter` ajustado: distingue "no auth" (`$context === null && $actorId === null` → 401) de "auth con uid=null" (service token con scope → check de permiso normal → 403 si missing). Test: nuevo `PermissionControllerTest::testIndexWithServiceTokenLackingIamAccessReturns403` reproduce el bug original (era 500) y fija contrato a 403. Suite Feature 117 verde · PHPStan 8 limpio.
- **[B7.5] Convenciones de paginación documentadas** (2026-05-06) — Tras revisión, `per_page` (paginated index) y `limit` (top-N cap) son **semánticamente distintos** y deben permanecer así. La auditoría había marcado `SlowRequestsQueryRequestDTO::limit` como inconsistencia, pero no aplica paginación porque no hay `page 2` para top-N. Creado `docs/tech/pagination.md` (EN+ES) con la convención, anti-patrones, y futuros (BaseIndexRequestDTO factor-out, cursor pagination cuando se active B3). Comentario aclaratorio en `SlowRequestsQueryRequestDTO`. arch-drift verde · suite 641 tests.
- **[B7.4] RFC 7807 Problem Details opt-in + ADR-010** (2026-05-06) — Builders aditivos en `ApiResponse`: `problemDetails(errors, title, status, type, instance, detail)` (puro 7807), `negotiateError(accept, ...)` (content-negotiation que retorna `{body, content_type}`), `clientPrefersProblemJson(accept)` (helper). Sobre default sigue intacto; opt-in por controller cuando se necesite. ADR-010 (EN+ES) documenta la decisión, q-aware parser mínimo, y trabajo futuro (URI scheme estable para `type`, posible problem+xml). 6 unit tests (21 total en `ApiResponseTest`). Suite 641 verde · PHPStan 8 limpio · arch-drift verde.
- **[B7.3] Idempotency-Key opt-in + ADR-009** (2026-05-06) — Migración `idempotency_keys` (PK por key, indexes en `expires_at` y `(actor_id, endpoint)`). `IdempotencyFilter` (alias `idempotency`, opt-in por ruta) implementa matriz completa: pass-through si no hay header / método read-only, 400 si key malformada, replicar respuesta cacheada si match con `Idempotent-Replay: true`, 409 + `Idempotency-Mismatch` si body hash distinto, persistir en `after()` solo en 2xx. SHA-256 sobre body crudo. Estado in-flight via static `$pending` (PHP-FPM single-request seguro). Race-safe: insert duplicado capturado en try/catch. ADR-009 (EN+ES) documenta matriz, justificación y futuros (cleanup job, TTL por ruta, Octane consideration). 6 feature tests cubriendo toda la matriz. Suite 635 verde · PHPStan 8 limpio · arch-drift verde.
- **[B7.2] Deprecation headers + /api/versions + ADR-008** (2026-05-06) — `Config\Api::$apiVersions` array map (status/deprecated_at/sunset_at/successor por versión). `DeprecationHeadersFilter` (alias `deprecationheaders`, en globals.after) emite `Deprecation` (RFC 8594 family), `Sunset` (RFC 8594), `Link: rel="successor-version"` (RFC 5988). Endpoint público `GET /api/versions` retorna catálogo. ADR-008 (EN+ES) documenta SLA defaults (18 meses soporte, 6 meses preaviso de sunset, 410 Gone post-sunset) y regla "no breaking changes en v1". 6 unit tests filter + 2 feature tests endpoint = 8 tests / 30 asserts. Suite total 629 verde · PHPStan 8 limpio · arch-drift verde · swagger.json regenerado.
- **[B7.1] AssignableRolesService extracción** (2026-05-06) — Movida la lógica anti-escalación de `UserController::assignableRoles()` (39 líneas de queries crudas + array_diff filtering) a `App\Services\Iam\AssignableRolesService`. Controller ahora 5 líneas con `handleRequest()` + `Services::assignableRolesService()`. Cableado en `IamDomainServices`. 7 integration tests (`tests/Integration/Services/Iam/AssignableRolesServiceTest.php`) pin contrato: role es asignable iff cada permiso del role ⊆ permisos efectivos del actor. Suite 621 verde · PHPStan 8 limpio · CS-Fixer limpio · arch-drift verde.
- **[API-002] POST /api/v1/auth/service-token** (2026-05-06) — Endpoint nuevo `POST /api/v1/auth/service-token` (OAuth client_credentials-style: 200 con `access_token`/`token_type`/`expires_in`/`scope`) protegido por `appKeyRequired` + `throttle` (mismo grupo que introspect). Service `ServiceTokenService` re-resuelve la API Key por hash via `ApiKeyMaterialService`+`ApiKeyRepository`, valida `application_id` (403 `Iam.apiKeyHasNoApplication` si NULL), carga `applications.code`, y delega a `JwtService::encodeServiceToken()` (método nuevo: payload con `sub: service:<code>` + `scope`, sin `uid`, con `jti` para revocabilidad). Permisos resueltos por `ApplicationPermissionsResolver` nuevo (espejo de `EffectivePermissionsResolver`, query directa a `permissions WHERE application_id`). TTL configurable via `JWT_SERVICE_TOKEN_TTL` (default 900s). 6 feature tests verdes (éxito + decode JWT, sin app_id, key inactiva, X-App-Key faltante/inválida, revocable) + 5 integration tests del resolver; suite completa 614 tests verdes; PHPStan level 8 limpio; OpenAPI regenerado con `appKeyAuth` security scheme. Plan: `~/.claude/plans/lee-ci4-api-starter-claude-md-y-luego-squishy-cray.md`.
- **[API-001] POST /api/v1/auth/introspect** (2026-05-06) — Endpoint nuevo `POST /api/v1/auth/introspect` (RFC 7662-style: siempre 200 con `valid: true|false`) que reusa `JwtService::decode()` + `TokenRevocationService::isRevoked()`. Filter nuevo `appKeyRequired` (alias) valida `X-App-Key`: 401 si falta, 403 si inválida/inactiva. Service `TokenIntrospectionService` puro (sin HTTP), wired en `TokenSecurityServices`. DTOs `IntrospectRequest/Response`, controller `IntrospectController`, doc OpenAPI con security scheme `appKeyAuth` (apiKey/header). 8 feature tests verdes (válido/expirado/revocado/malformado, X-App-Key faltante/inválida/inactiva, body sin token); suite completa 603 tests verdes; PHPStan level 8 limpio. Plan: `~/.claude/plans/lee-ci4-api-starter-claude-md-y-luego-parsed-flame.md`.
- **[API-003] Reglas de modificación de usuarios** (2026-05-06) — Endpoint nuevo `PATCH /api/v1/auth/me` con DTO `UpdateMeRequestDTO` (allowlist `first_name`/`last_name`/`avatar_url`) y `UpdateSelfProfileAction`. `UpdateUserAction` rechaza cambios de email para actores no-superadmin (`Iam.cannotModifyEmail`). Lado admin (otro repo): Profile sin gate `users.write`, vista con email solo lectura, `UserUpdateRequest` filtra email salvo superadmin. Suite completa verde (595 API + 300 admin); OpenAPI regenerado. Plan: `~/.claude/plans/creo-que-nos-falta-kind-neumann.md`.

---

## 🏗️ Contratos de arquitectura

> Restricciones que se deben respetar siempre al tocar este repo. No negociables.

- **DTO-First:** toda entrada y salida de Controllers usa DTOs. Nunca arrays raw. Request DTOs extienden `BaseRequestDTO`, Response DTOs son clases tipadas.
- **Services puros:** los Services no conocen HTTP ni `$request`. Reciben DTOs, devuelven DTOs o lanzan excepciones de dominio.
- **Controllers delgados:** usar `handleRequest()` de `ApiController`. Sin lógica de negocio en el Controller.
- **Separador de permisos:** punto `.` (NO dos puntos `:`). Ejemplo: `users.write`, `iam.admin-access`. Razón: `Filters::getCleanName()` hace `explode(':')` y trunca silenciosamente.
- **Rutas por dominio:** viven en `app/Config/Routes/v1/<dominio>.php`. Las de auth en `auth.php`.
- **Tests:** todo endpoint nuevo necesita al menos un test Feature. Correr con `vendor/bin/phpunit tests/Feature`.
- **CRUD nuevo:** usar `bash vendor/bin/make-crud.sh` siempre. Nunca crear DTOs manualmente.
- **OpenAPI:** correr `php spark swagger:generate` al terminar cualquier endpoint nuevo.
- **Migraciones:** nunca modificar migraciones existentes. Crear nueva migración para cualquier cambio de schema.
