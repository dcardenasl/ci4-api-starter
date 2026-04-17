# CRUD Desde Cero (Playbook del Template)

Guía canónica para crear un recurso CRUD nuevo en este template.

Este flujo es **recomendado por defecto**:
1. Generar scaffold primero con `bin/make-crud.sh` (o `php spark make:crud` en modo interactivo)
2. Validar el wiring con `module:check`
3. Aplicar migración, reiniciar servidor y regenerar OpenAPI
4. Personalizar solo lo que reglas de negocio exijan
5. Cerrar tests y quality gates

La creación manual de CRUD sigue siendo válida cuando se requiere estructura custom.

## 1. Pre-Checklist

1. Definir nombre del recurso (singular): `Product`
2. Definir dominio: `Catalog`
3. Definir slug de ruta (plural kebab): `products`
4. Definir modelo de acceso (lectura pública, escritura admin, etc.)
5. Definir esquema mínimo de tabla y requerimientos de auditoría
## 2. Scaffold Primero

El motor `make:crud` genera todas las capas en un solo paso (Migración, Entidad, Modelo, DTOs Request/Response, Servicio, Interface, Controller, clase OpenAPI de endpoints, archivos i18n, esqueletos de tests, wiring de servicios y archivo de rutas).

### 2.1 Recomendado: `bin/make-crud.sh` (shell-safe)

```bash
bash bin/make-crud.sh Product Catalog \
  'name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required' \
  yes
```

Firma: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Por qué preferir el wrapper:
- Las comillas simples evitan que el shell consuma los `|` dentro de `--fields`
- Ejecuta `composer cs-fix` automáticamente después de generar (mantiene contentos los pre-commit hooks)
- Imprime los comandos de seguimiento con los placeholders ya resueltos
- Seguro en entornos no-TTY (pipelines de CI, Claude Code, scripts de automatización)

### 2.2 Alternativa: `php spark make:crud` (interactivo)

Cuando quieras que el motor te pregunte por cada campo:

```bash
php spark make:crud Product --domain Catalog
```

La variante `--fields` también funciona, pero debe citarse con comillas simples:

```bash
php spark make:crud Product --domain Catalog --fields='name:string:required|searchable,price:decimal:required|filterable'
```

> En entornos no interactivos, si olvidas citar los pipes el comando pierde silenciosamente todos los modificadores y espera input interactivo que nunca llega. Esta es la razón #1 por la que `bin/make-crud.sh` es preferido.

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
