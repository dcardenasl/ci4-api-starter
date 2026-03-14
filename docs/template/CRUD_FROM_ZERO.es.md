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

El comando `make:crud` maneja la creación de todas las capas (Migración, Entidad, Modelo, DTOs, Servicio, Controlador).

### Opción A: Modo Interactivo (Recomendado)
Ejecuta el comando solo con el recurso y el dominio. El sistema te preguntará por los detalles de cada campo.

```bash
php spark make:crud Product --domain Catalog
```

### Opción B: Modo CLI (Rápido)
Define tu esquema en una sola cadena usando la opción `--fields`.

```bash
php spark make:crud Product --domain Catalog --fields="name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required"
```

**Guía de Sintaxis de Campos:**
- Formato: `nombre:tipo:opciones`
- Tipos: `string`, `text`, `int`, `bool`, `decimal`, `email`, `date`, `datetime`, `fk`, `json`.
- Opciones (separadas por pipe): `required`, `nullable`, `searchable`, `filterable`, `fk:nombre_tabla`.

Qué genera el scaffold:
1. Archivos de migración de base de datos
2. Controller, Service, Interface
3. DTOs Request/Response
4. Model/Entity
5. Placeholder OpenAPI de endpoints (`app/Documentation/...`)
6. Archivos i18n (`en` y `es`)
7. Esqueletos de tests Unit/Integration/Feature
8. Registro de servicio en `app/Config/Services.php` (si faltaba)

Qué **no** genera:
1. Migraciones de base de datos
2. Repositorio específico por dominio (`*Repository`)
3. Reglas finales de negocio y validaciones específicas

## 3. Validar Bootstrap

```bash
php spark module:check Product --domain Catalog
```

`module:check` valida artefactos del módulo y wiring básico (`Services.php`, referencia en rutas), pero **no** valida existencia ni contenido de migraciones.

## 4. Ejecutar Migración(es)

Dado que el scaffold genera la migración automáticamente, solo necesitas revisarla y aplicarla:

```bash
php spark migrate
```

Luego implementar:
1. Revisar columnas y constraints finales
2. Agregar índices requeridos si es necesario
3. Asegurar que soft deletes estén configurados como se desea

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
   - se requieren métodos de consulta custom para claridad/testability

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

### ¿Cuándo ejecutar migraciones?
Después de ejecutar `make:crud` y validar el esqueleto con `module:check`.

### ¿`make:crud` crea migraciones?
Sí. Utiliza un esquema único para sincronizar base de datos, DTOs y servicios.

### ¿Cuándo necesito repositorio dedicado?
Cuando el CRUD genérico no alcanza y las consultas/reglas de persistencia del dominio se vuelven explícitas/complejas.

### ¿`make:crud` es obligatorio?
Es el estándar recomendado por defecto. La creación manual está permitida para requerimientos custom.
