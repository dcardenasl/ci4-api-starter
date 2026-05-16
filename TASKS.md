# TASKS — ci4-api-starter

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md`.
> Última actualización: 2026-05-16 (API-010 ✅, API-013 ✅, API-012 partial — Docker entrypoint listo)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — backlog abajo)*

---

## ✅ Completados recientemente (mover a TASKS_ARCHIVE.md en el próximo corte)

- [API-010] ✅ 2026-05-15 — `GET /iam/users/{id}/permissions?app=<code>` implementado. `UserPermissionsController`, `ListUserPermissionsRequestDTO`, `UserPermissionsResponseDTO`, `ApplicationSummary`, `UserPermissionsService`, OpenAPI annotations + feature + unit tests.
- [API-013] ✅ 2026-05-15 — CI matrix extendida a PHP 8.4 + CI4 4.6/4.7 en `.github/workflows/ci.yml`.

---

## ⚪ Backlog

- [API-012] Docker out-of-the-box — `docker/entrypoint.sh` idempotente ✅ (2026-05-15). Pendiente: orquestación cross-repo en `ci4-kickstart` (coordinada con kickstart v1.1.0+).

---

## ⚠️ Fuera de alcance / señales

- [API-014] Soporte multi-tenant nativo — decisión registrada en `docs/adr/ADR-011-multi-tenancy-out-of-scope.md`. Reactivar solo si aparece una señal real (tenant con SLA propio, aislamiento físico requerido, etc.).

---

## 🏗️ Contratos de arquitectura

- **DTO-First:** toda entrada y salida de Controllers usa DTOs. Nunca arrays raw.
- **Services puros:** no conocen HTTP ni `$request`. Reciben DTOs, devuelven DTOs o lanzan excepciones de dominio.
- **Controllers delgados:** usar `handleRequest()` de `ApiController`. Sin lógica de negocio.
- **Separador de permisos:** punto `.` (NO `:`). Razón: `Filters::getCleanName()` hace `explode(':')` y trunca silenciosamente.
- **Rutas por dominio:** `app/Config/Routes/v1/<dominio>.php`.
- **Tests:** todo endpoint nuevo necesita al menos un test Feature.
- **CRUD nuevo:** usar `bash vendor/bin/make-crud.sh` siempre. Nunca crear DTOs manualmente.
- **OpenAPI:** correr `php spark swagger:generate` al terminar cualquier endpoint nuevo.
- **Migraciones:** nunca modificar migraciones existentes. Nueva migración para cualquier cambio de schema.
