# TASKS — ci4-api-starter

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md`.
> Última actualización: 2026-05-07 (API-015/016/017 cerradas, consumo ci4-api-core v0.2.0 completo)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — backlog abajo, siguiente paso cross-repo es CORE-006)*

---

## ⚪ Backlog

- [API-010] `GET /iam/users/{id}/permissions?app=<code>` — permisos de usuario filtrados por app de dominio
- [API-011] Publicar `ci4-api-core` en Packagist (actualmente path repo) — bloqueado por CORE-006
- [API-012] Docker out-of-the-box — Dockerfile + docker-compose api+admin+mysql
- [API-013] CI/CD pipeline de ejemplo — GitHub Actions
- [API-014] Soporte multi-tenant nativo — fuera de alcance v1.x, ver ADR-011

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
