---
name: ci4-api-crud-expert
description: Experto en este repositorio CodeIgniter 4 API Starter para diseñar e implementar recursos CRUD end-to-end siguiendo la arquitectura Modernizada (Declarative DTO-First). Usar cuando se pida crear, extender, corregir o documentar un CRUD nuevo en esta base, incluyendo DTOs autovalidados, servicios genéricos, integrated OpenAPI y pruebas Unit/Feature.
---

# CI4 API CRUD Expert (Arquitectura Modernizada)

Este skill define el estándar de oro para crear recursos en este repositorio, priorizando la inmutabilidad, el tipado estricto y el desacoplamiento total mediante una arquitectura declarativa.

## Flujo de Implementación Obligatorio

1. **Diseño de Datos:**
   - Crear **Migración** con timestamps y soft deletes.
   - Crear **Entidad** con `$casts`.
   - Crear **Modelo** con traits `Filterable`, `Searchable`, `Auditable`.

2. **Capa de Contrato (DTOs Autovalidados):**
   - Crear **Request DTOs** (`app/DTO/Request/`) extendiendo de `BaseRequestDTO`.
   - Definir validación en el método `rules()`. El constructor valida automáticamente.
   - Crear **Response DTOs** (`app/DTO/Response/`) con atributos OpenAPI `#[OA\Property]`.

3. **Lógica de Negocio (Servicio Puro & Transaccional):**
   - Definir **Interface** en `app/Interfaces/`.
   - Implementar **Servicio** heredando de `BaseCrudService`.
   - Usar el trait `HandlesTransactions` para operaciones de escritura.
   - **Regla de Oro:** El servicio no conoce la capa HTTP. Recibe DTOs y devuelve DTOs/Arrays.
   - **Propagación de Identidad:** Inyectar automáticamente `SecurityContext` en los métodos del servicio vía `handleRequest`.
   - Registrar en `app/Config/Services.php`.

4. **Capa de Transporte (Controller Declarativo):**
   - Crear **Controller** extendiendo `ApiController`.
   - Usar `handleRequest('serviceMethod', RequestDTO::class)` para orquestación automática e inyección de contexto de seguridad.
   - Definir **Rutas** en `app/Config/Routes.php`.

5. **Infraestructura:**
   - Crear archivos de **Idioma** (`en/` y `es/`) para cada mensaje.
   - Definir endpoints en `app/Documentation/{Domain}/` (los schemas ya viven en los DTOs).
   - Generar Swagger: `php spark swagger:generate`.

6. **Validación de Calidad:**
   - **Pruebas Unitarias:** Validar lógica del servicio con mocks de dependencias.
   - **Pruebas Feature:** Usar `actAs()` y `ContextHolder` para simular identidad. Validar respuesta JSON (clave `data`) y códigos HTTP semánticos (201, 202, 422).
   - **Base de Datos:** Usar SQLite para tests rápidos e independientes.
   - `composer quality` debe pasar al 100%.

## Reglas Inquebrantables (Nuevos Estándares)

- ❌ **PROHIBIDO** usar `InputValidationService` o `validateOrFail` manual (Legacy).
- ❌ **PROHIBIDO** retornar `ApiResponse` desde la capa de servicio.
- ✅ **OBLIGATORIO** extender de `BaseRequestDTO` para validación de entrada.
- ✅ **OBLIGATORIO** usar `handleRequest` en controladores para evitar boilerplate.
- ✅ **OBLIGATORIO** envolver todas las respuestas exitosas en la clave `data` (Automático vía `ApiController`).

## Referencias
- Pasos detallados: `references/crud-playbook.md`.
- Snippets de código: `references/crud-snippets.md`.
