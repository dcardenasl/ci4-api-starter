# CRUD Desde Cero (Playbook del Template)

Guía canónica para crear un recurso CRUD nuevo en este template.

Este flujo es **recomendado por defecto**:
1. Generar scaffold primero con `php spark make:crud ...`
2. Personalizar archivos generados
3. Crear migración y cerrar persistencia real
4. Cerrar tests/documentación/quality gates

La creación manual de CRUD sigue siendo válida cuando se requiere estructura custom.

## 1. Pre-Checklist

1. Definir nombre del recurso (singular): `Product`
2. Definir dominio: `Catalog`
3. Definir slug de ruta (plural kebab): `products`
4. Definir modelo de acceso (lectura pública, escritura admin, etc.)
5. Definir esquema mínimo de tabla y requerimientos de auditoría

## 2. Scaffold Primero

```bash
php spark make:crud Product --domain Catalog --route products
```

Qué genera el scaffold:
1. Controller, Service, Interface
2. DTOs Request/Response
3. Model/Entity
4. Placeholder OpenAPI de endpoints (`app/Documentation/...`)
5. Archivos i18n (`en` y `es`)
6. Esqueletos de tests Unit/Integration/Feature
7. Registro de servicio en `app/Config/Services.php` (si faltaba)

Qué **no** genera:
1. Migraciones de base de datos
2. Repositorio específico por dominio (`*Repository`)
3. Reglas finales de negocio y validaciones específicas

## 3. Validar Bootstrap

```bash
php spark module:check Product --domain Catalog
```

`module:check` valida artefactos del módulo y wiring básico (`Services.php`, referencia en rutas), pero **no** valida existencia ni contenido de migraciones.

## 4. Crear Migración(es)

Crear migración justo después de validar el scaffold y antes de cerrar lógica de negocio:

```bash
php spark make:migration CreateProductsTable
```

Luego implementar:
1. Columnas y constraints finales
2. Índices requeridos
3. `created_at`, `updated_at`, `deleted_at` (si aplica soft delete)

Aplicar:

```bash
php spark migrate
```

## 5. Alinear Capa de Persistencia

1. Ajustar `Entity` (`casts`/`dates`) según migración
2. Ajustar `Model`:
   - `allowedFields`
   - `validationRules`
   - `searchableFields`, `filterableFields`, `sortableFields`
   - traits (`Filterable`, `Searchable`, `Auditable`) cuando aplique

## 6. Cerrar Contratos DTO

1. Request DTOs extienden `BaseRequestDTO` y se mantienen `readonly`
2. Implementar `rules()`, `map()`, `toArray()` completos
3. Response DTO con atributos OpenAPI `#[OA\Property]` y `fromArray()`
4. Mantener DTOs alineados al contrato API (sin exponer campos internos)

## 7. Servicio + Estrategia de Repositorio

Reglas del servicio:
1. Servicio puro (sin construir respuestas HTTP)
2. Lecturas retornan DTOs; comandos retornan `OperationResult`
3. Usar transacciones en escrituras

Estrategia por defecto:
1. Usar `GenericRepository` vía `RepositoryInterface` para CRUD estándar
2. Crear `*RepositoryInterface` + implementación dedicada solo cuando:
   - existen consultas de dominio no triviales
   - hay reglas de persistencia reutilizables entre servicios
   - se requieren métodos de consulta custom para claridad/testabilidad

## 8. Registrar Dependencias

1. Confirmar registro de servicio en `app/Config/Services.php`
2. Si hay repositorio dedicado, registrar también sus factories
3. Mantener DI tipada por interfaces cuando corresponda

## 9. Controller, Routes, OpenAPI e i18n

1. Controller extiende `ApiController`
2. Resolver servicio en `resolveDefaultService()`
3. Usar patrón `handleRequest('method', RequestDTO::class)`
4. Agregar/verificar rutas en `app/Config/Routes.php` con filtros correctos
5. Completar docs de endpoints en `app/Documentation/{Domain}/...`
6. Mantener paridad de idioma en `app/Language/en` y `app/Language/es`

## 10. Testing y Quality Gates

1. Completar Unit tests (comportamiento/contratos de servicio)
2. Completar Integration tests (modelo/persistencia)
3. Completar Feature tests (status HTTP + JSON + autorización)
4. Ejecutar:

```bash
php spark tests:prepare-db
composer quality
php spark swagger:generate
```

## FAQ

### ¿Cuándo crear migraciones?
Después de ejecutar `make:crud` y validar el esqueleto con `module:check`, y antes de cerrar el comportamiento final del dominio.

### ¿`make:crud` crea migraciones?
No.

### ¿Cuándo necesito repositorio dedicado?
Cuando el CRUD genérico no alcanza y las consultas/reglas de persistencia del dominio se vuelven explícitas/complejas.

### ¿`make:crud` es obligatorio?
Es el estándar recomendado por defecto. La creación manual está permitida para requerimientos custom.
