# TASKS_ARCHIVE — ci4-api-starter

> Historial de tareas completadas. Movido desde TASKS.md para mantener el tracker activo liviano.
> Última actualización: 2026-05-07

---

## ✅ Enterprise hardening (Milestone B5–B11, 2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| B7.1 | `AssignableRolesService` extracción del controller anti-pattern | ✅ |
| B7.2 | Headers de deprecación + `/api/versions` + ADR-008 (EN+ES) | ✅ |
| B7.3 | `Idempotency-Key` opt-in + migración `idempotency_keys` + ADR-009 (EN+ES) | ✅ |
| B7.4 | RFC 7807 Problem Details opt-in + ADR-010 (EN+ES) | ✅ |
| B7.5 | Convención de paginación documentada en `docs/tech/pagination.md` (EN+ES) | ✅ |
| B9.2 | `GoogleLoginSoftDeletedUserTest` (2 tests, contrato de reactivación) | ✅ |
| B10.1 | `CorrelationIdFilter` + `RequestIdHolder` + propagación en ApiClient | ✅ |
| B11.1 | ADR-011 (multi-tenancy out-of-scope) + ADR-012 (config runtime mutability) EN+ES | ✅ |
| B11.2 | 4 runbooks (rotate JWT, failed migration, upgrade CI4, token-leak incident) EN+ES | ✅ |

---

## ✅ Endpoints de integración hub↔domain (2026-05-06/07)

| ID | Descripción | Estado |
|---|---|---|
| API-001 | `POST /api/v1/auth/introspect` — introspección JWT (RFC 7662-style). Filter `appKeyRequired`, `TokenIntrospectionService`, DTOs, doc OpenAPI. 8 feature tests. 603 tests verdes. | ✅ |
| API-002 | `POST /api/v1/auth/service-token` — M2M auth sin usuario. `ServiceTokenService`, `ApplicationPermissionsResolver`, `JwtService::encodeServiceToken()`, TTL configurable. 6 feature + 5 integration tests. 614 tests verdes. | ✅ |
| API-003 | Reglas de modificación de usuarios: `PATCH /api/v1/auth/me` (allowlist first_name/last_name/avatar_url). Email inmutable salvo superadmin. `assertNotSelf()` en PUT. | ✅ |
| API-005 | Bug: `JwtAuthFilter` crasheaba con service tokens (uid undefined). Null-safe + `PermissionFilter` distingue 401 vs 403. | ✅ |
| API-006 | `/auth/introspect` re-resuelve scope según `X-App-Key` del caller. `EffectivePermissionsResolver(uid, application_id)`. | ✅ |
| API-007 | `apps:bootstrap --create-api-key`: genera API key activa, output parseable `API_KEY=apk_...`. 4 integration tests. Desbloquea KICK-001. | ✅ |

---

## ✅ Deudas post-port + consumo ci4-api-core v0.2.0 (2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| API-015 | `HandlesTranslations` cascade delete — `afterDelete()` llama `TranslationModel::deleteForEntity()` dentro de la transacción de `BaseCrudService::destroy`. Integration test store/update/delete completo. | ✅ |
| API-016 | `GalleryService` → `PivotRepositoryInterface`. `PivotRepository` abstract, `findByIds` en `FileRepository`. Integration test end-to-end con fixture pivot table. Whitelist de `ServiceModelDependencyConventionsTest` reducida. | ✅ |
| API-017 | `DataBag` eliminado. `ResponseMapper::map()` acepta `object\|array` directamente (CORE-009). `HandlesTranslations::mapToResponse` pasa array directo. | ✅ |

**Refactor de consumo** (sin ID de tarea — trabajo derivado de ci4-api-core v0.2.0):
- Helpers procedurales consumidos desde `dcardenasl/ci4-api-core`
- Cadena de audit consumida desde core
- HTTP filters y logging stack consumidos desde core
- Mappers y utilidades de support consumidas desde core
- `BaseRepository` consumido desde core
- Exception handlers HTTP consumidos desde core
- `Filterable`, `Searchable`, `QueryBuilder` consumidos desde core
- Fixtures de tests actualizados a imports de `dcardenasl/ci4-api-core`
- `composer.lock` + swagger regenerados

---

*TASKS_ARCHIVE · ci4-api-starter · 2026-05-07*
