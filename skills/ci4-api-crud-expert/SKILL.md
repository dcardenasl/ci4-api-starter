---
name: ci4-api-crud-expert
description: Experto en este repositorio CodeIgniter 4 API Starter para diseñar e implementar recursos CRUD end-to-end. Usar cuando se pida crear, extender, corregir o documentar un CRUD nuevo en esta base, incluyendo migration, entity, model, interface, service, registro en Config\\Services, controller, rutas, OpenAPI en app/Documentation y pruebas Unit/Integration/Feature.
---

# CI4 API CRUD Expert

Usar este flujo para crear CRUDs que respeten los patrones reales del proyecto.

## Workflow base

1. Leer `docs/AGENT_QUICK_REFERENCE.md` para checklist rápida.
2. Leer `app/Config/Routes.php`, `app/Controllers/ApiController.php` y un ejemplo existente (`User` o `ApiKey`) antes de editar.
3. Definir nombre singular/plural del recurso y permisos de lectura/escritura.
4. Crear migration con timestamps y decidir soft delete (`deleted_at`) o hard delete.
5. Crear entity con `$casts`, `$dates` y `$hidden` para campos sensibles.
6. Crear model con:
   - `use Filterable, Searchable` (y `Auditable` si aplica).
   - `$allowedFields`, `$validationRules`, `$searchableFields`, `$filterableFields`, `$sortableFields`.
7. Crear interface de servicio en `app/Interfaces/{Resource}ServiceInterface.php` con `index/show/store/update/destroy`.
8. Crear servicio en `app/Services/{Resource}Service.php`:
   - Inyectar model en constructor.
   - Reutilizar `AppliesQueryOptions` y `ValidatesRequiredFields` cuando aplique.
   - Devolver siempre `ApiResponse::*`.
   - Lanzar excepciones de dominio (`BadRequestException`, `NotFoundException`, `ValidationException`, etc.).
9. Registrar servicio en `app/Config/Services.php` como `{resource}Service`.
10. Crear controller en `app/Controllers/Api/V1/{Resource}Controller.php` extendiendo `ApiController` y definiendo `protected string $serviceName`.
11. Agregar rutas en `app/Config/Routes.php` dentro de `api/v1` con filtros (`jwtauth`, `roleauth:admin`, `throttle`/`authThrottle`) según el caso.
12. Crear/actualizar idioma en `app/Language/en/*.php` y `app/Language/es/*.php`.
13. Documentar endpoints y schemas en `app/Documentation/*` (no en controllers).
14. Implementar pruebas:
   - Unit: `tests/Unit/Services/{Resource}ServiceTest.php`
   - Integration: `tests/Integration/Models/{Resource}ModelTest.php`
   - Feature: `tests/Feature/Controllers/{Resource}ControllerTest.php`
15. Ejecutar validaciones:
   - `vendor/bin/phpunit tests/Unit`
   - `vendor/bin/phpunit tests/Integration`
   - `vendor/bin/phpunit tests/Feature`
   - `composer cs-check`
   - `composer phpstan`
   - `php spark swagger:generate`

## Reglas del proyecto que no romper

- Mantener controller delgado: delegar lógica al servicio.
- No devolver modelos/entities crudos desde servicio; devolver arrays `ApiResponse`.
- No usar SQL crudo para CRUD estándar; usar model/query builder.
- Usar `QueryBuilder` + traits para `filter`, `search`, `sort`, `page`, `limit`.
- Mantener convención de namespace `App\Controllers\Api\V1`.
- Mantener documentación OpenAPI en `app/Documentation/`.
- En Integration/Feature con DB, establecer `protected $namespace = 'App'`.

## Qué cargar según tarea

- Para pasos detallados por archivo y orden de implementación: ver `references/crud-playbook.md`.
- Para snippets mínimos por tipo de archivo: ver `references/crud-snippets.md`.
