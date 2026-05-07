# TASKS — ci4-api-starter

> Fuente de verdad para trabajo en este repo.
> Gestionado desde Cowork/VentureOS. Ejecutado desde Claude Code.
> Última actualización: 2026-05-06

---

## 🔴 En progreso

*(vacío — ninguna tarea activa)*

---

## 🟡 Próximo (ordenado por prioridad)

*(vacío — siguiente tarea por priorizar)*

---

## ⚪ Backlog

- [API-010] `GET /iam/users/{id}/permissions?app=<code>` — permisos de un usuario filtrados por app de dominio (útil para domain apps que quieren verificar permisos sin introspect completo)
- [API-011] Publicar `ci4-api-crud-maker` en Packagist (actualmente VCS/path repo)
- [API-012] Docker out-of-the-box — Dockerfile + docker-compose para dev
- [API-013] CI/CD pipeline de ejemplo — GitHub Actions
- [API-014] Soporte multi-tenant nativo en el modelo de usuarios

---

## ✅ Completadas recientes

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
