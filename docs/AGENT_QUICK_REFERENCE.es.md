# Referencia Rápida para Agentes - CI4 API Starter

**Objetivo**: guía corta para agentes que implementan CRUD en esta base.

## 1. Flujo DTO-First

```
HTTP Request → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiController::respond() → JSON
```

## 2. Checklist CRUD

1. Ejecutar scaffold:
```bash
php spark make:crud {Name} --domain {Domain} --route {endpoint}
```
2. Completar migración, entidad y modelo.
3. Completar Request DTOs (`rules/messages/map`).
4. Completar Response DTO.
5. Servicio puro:
   - lecturas -> DTOs
   - comandos -> `OperationResult`
6. Registrar servicio en `app/Config/Services.php`.
7. Controlador delgado con `handleRequest('method', RequestDTO::class)`.
8. Rutas + filtros + i18n (`en` y `es`).
9. Tests Unit/Feature/Integration según corresponda.
10. Ejecutar `composer quality`.

## 3. Reglas de arquitectura

1. No retornar `ApiResponse` desde servicios.
2. No pasar arrays crudos entre controller y service.
3. No reimplementar pipeline HTTP en controladores.
4. `CrudServiceContract::index()` debe retornar DTO paginado.

## 4. Excepciones comunes

- `NotFoundException` (404)
- `AuthenticationException` (401)
- `AuthorizationException` (403)
- `ValidationException` (422)
- `BadRequestException` (400)
- `ConflictException` (409)

## 5. Fuente de verdad

1. `docs/template/ARCHITECTURE_CONTRACT.md`
2. `docs/template/MODULE_BOOTSTRAP_CHECKLIST.md`
3. `docs/template/QUALITY_GATES.md`
