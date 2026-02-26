---
name: ci4-api-crud-expert
description: Experto en este repositorio CodeIgniter 4 API Starter para diseñar e implementar recursos CRUD end-to-end siguiendo la arquitectura Million-Dollar (DTO-First). Usar cuando se pida crear, extender, corregir o documentar un CRUD nuevo en esta base, incluyendo DTOs, pure services, integrated OpenAPI y pruebas Unit/Feature.
---

# CI4 API CRUD Expert (Arquitectura Millonaria)

Este skill define el estándar de oro para crear recursos en este repositorio, priorizando la inmutabilidad, el tipado estricto y el desacoplamiento total.

## Flujo de Implementación Obligatorio

1. **Diseño de Datos:**
   - Crear **Migración** con timestamps y soft deletes.
   - Crear **Entidad** con `$casts`.
   - Crear **Modelo** con traits `Filterable`, `Searchable`, `Auditable`.

2. **Capa de Contrato (DTOs):**
   - Crear **Request DTOs** (`app/DTO/Request/`) usando clases `readonly` de PHP 8.2.
   - Implementar auto-validación en el constructor del DTO vía `validateOrFail()`.
   - Crear **Response DTOs** (`app/DTO/Response/`) con atributos OpenAPI `#[OA\Property]`.

3. **Lógica de Negocio (Servicio Puro):**
   - Definir **Interface** en `app/Interfaces/`.
   - Implementar **Servicio** en `app/Services/`.
   - **Regla de Oro:** El servicio no conoce `ApiResponse`. Recibe DTOs y devuelve DTOs/Entidades. Lanza excepciones para errores.
   - Registrar en `app/Config/Services.php`.

4. **Capa de Transporte (Controller):**
   - Crear **Controller** extendiendo `ApiController`.
   - Usar `getDTO()` para mapear la entrada.
   - Usar `handleRequest(fn() => ...)` para delegar al servicio.
   - Definir **Rutas** con filtros adecuados (`jwtauth`, `roleauth`, `throttle`).

5. **Infraestructura:**
   - Crear archivos de **Idioma** (`en/` y `es/`) para cada mensaje de usuario.
   - Definir endpoints en `app/Documentation/{Domain}/` (los schemas ya viven en los DTOs).
   - Generar Swagger: `php spark swagger:generate`.

6. **Validación:**
   - **Pruebas Unitarias:** Validar lógica del servicio y retornos de DTO.
   - **Pruebas Feature:** Validar estructura final JSON y códigos HTTP.
   - `composer quality` debe pasar al 100%.

## Reglas Inquebrantables

- ❌ NO usar arreglos asociativos genéricos para datos de negocio; usar DTOs.
- ❌ NO retornar `ApiResponse` desde la capa de servicio.
- ❌ NO añadir anotaciones `@OA\Schema` en archivos externos si el DTO ya existe.
- ✅ SIEMPRE usar `readonly` para DTOs e inyección de dependencias.
- ✅ SIEMPRE normalizar la salida a snake_case a través de `ApiController`.

## Referencias
- Pasos detallados: `references/crud-playbook.md`.
- Snippets de código: `references/crud-snippets.md`.
