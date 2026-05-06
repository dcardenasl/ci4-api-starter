# TASKS — ci4-api-starter

> Fuente de verdad para trabajo en este repo.
> Gestionado desde Cowork/VentureOS. Ejecutado desde Claude Code.
> Última actualización: 2026-05-06

---

## 🔴 En progreso

*(vacío — ninguna tarea activa)*

---

## 🟡 Próximo (ordenado por prioridad)

### [API-001] POST /api/v1/auth/introspect
**Prioridad:** Alta — desbloquea ci4-domain-starter

**Objetivo:** Exponer la validación de JWT como endpoint HTTP para que apps de dominio verifiquen tokens de usuarios sin necesidad de compartir el JWT secret.

**Contexto:**
- La lógica de validación ya existe en `JwtAuthFilter` y `JwtService::decode()` — solo hay que exponerla
- El JWT ya lleva el claim `scope` con los permisos granulares del usuario
- El endpoint debe estar protegido con `X-App-Key` válida (el dominio se identifica con su API Key), no requiere JWT propio
- Sigue el patrón DTO-first del proyecto: RequestDTO + ResponseDTO, Controller delgado, lógica en Service
- Rutas de auth viven en `app/Config/Routes/v1/auth.php`

**Criterios de aceptación:**
- [ ] Ruta `POST /api/v1/auth/introspect` registrada en `app/Config/Routes/v1/auth.php`, protegida con `throttle` filter
- [ ] `IntrospectController.php` en `app/Controllers/Api/V1/Auth/`
- [ ] `IntrospectRequestDTO` acepta `token: string` en el body
- [ ] `IntrospectResponseDTO` devuelve `{ valid: bool, uid: int|null, permissions: string[], exp: int|null, error: string|null }`
- [ ] Devuelve 200 con `valid: false` si el token está expirado o es inválido (no lanzar excepción)
- [ ] Devuelve 200 con `valid: false` si el token está en blacklist (revocado)
- [ ] Devuelve 401 si falta el header `Authorization`
- [ ] Devuelve 403 si la `X-App-Key` es inválida o está inactiva
- [ ] Test Feature cubre: token válido, token expirado, token revocado, API Key faltante, API Key inválida
- [ ] `php spark swagger:generate` sin errores tras el cambio

**Rama sugerida:** `feature/auth-introspect`

---

### [API-002] POST /api/v1/auth/service-token
**Prioridad:** Alta — necesario para jobs background en ci4-domain-starter

**Objetivo:** Permitir que una app de dominio se autentique sin usuario humano y obtenga un JWT de corta duración con scope limitado a los permisos de su aplicación.

**Contexto:**
- Las API Keys existen (`app/Database/Migrations/2026-02-18-000001_CreateApiKeysTable.php`) pero solo controlan rate limiting, no otorgan identidad
- La tabla `api_keys` tiene `application_id` FK desde migración 2026-05-03 — verificar que esté wired en `ApiKeyService`
- El JWT resultante usa `uid: null`, `sub: service:<app_code>`, scope limitado a los permisos de la aplicación registrada
- TTL corto: 15 minutos. Sin refresh token (JWTs de servicio son descartables)
- Requiere que la app esté registrada con `php spark apps:bootstrap <code>` antes de usar este endpoint

**Criterios de aceptación:**
- [ ] Ruta `POST /api/v1/auth/service-token` registrada, protegida con `throttle`
- [ ] Acepta `X-App-Key` en header para identificar la app
- [ ] Verifica que la API Key tenga `application_id` asociado y esté activa
- [ ] Obtiene los permisos de la aplicación (no del usuario) desde el RBAC
- [ ] Genera JWT con `sub: service:<app_code>`, `scope: [permisos de la app]`, `exp: now + 15min`
- [ ] Devuelve 403 si la API Key no tiene `application_id` asociado
- [ ] Devuelve 403 si la API Key está inactiva
- [ ] Test Feature cubre: Key con app registrada, Key sin application_id, Key inactiva
- [ ] `php spark swagger:generate` sin errores tras el cambio

**Dependencia:** API-001 recomendado primero (misma sesión o anterior)
**Rama sugerida:** `feature/service-auth`

---

## ⚪ Backlog

- [API-010] `GET /iam/users/{id}/permissions?app=<code>` — permisos de un usuario filtrados por app de dominio (útil para domain apps que quieren verificar permisos sin introspect completo)
- [API-011] Publicar `ci4-api-crud-maker` en Packagist (actualmente VCS/path repo)
- [API-012] Docker out-of-the-box — Dockerfile + docker-compose para dev
- [API-013] CI/CD pipeline de ejemplo — GitHub Actions
- [API-014] Soporte multi-tenant nativo en el modelo de usuarios

---

## ✅ Completadas recientes

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
