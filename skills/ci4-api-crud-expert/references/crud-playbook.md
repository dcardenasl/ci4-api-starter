# CRUD Playbook (CI4 API Starter)

## 1) Preparación

- Inspeccionar ejemplos reales:
  - `app/Controllers/Api/V1/UserController.php`
  - `app/Services/UserService.php`
  - `app/Models/UserModel.php`
  - `app/Controllers/Api/V1/ApiKeyController.php`
  - `app/Services/ApiKeyService.php`
  - `app/Models/ApiKeyModel.php`
- Confirmar filtros/rutas en `app/Config/Routes.php`.

## 2) Orden recomendado de implementación

1. Migration
2. Entity
3. Model
4. Service Interface
5. Service
6. Services config
7. Controller
8. Routes
9. Language files
10. OpenAPI docs
11. Tests (Unit, Integration, Feature)

## 3) Migration

- Comando: `php spark make:migration Create{Resources}Table`
- Incluir `id`, `created_at`, `updated_at`.
- Incluir `deleted_at` solo si el recurso será soft delete.
- Agregar índices para campos filtrables y búsqueda frecuente.
- Ejecutar: `php spark migrate`

## 4) Entity

- Definir `protected $casts`.
- Definir `protected $dates`.
- Definir `protected array $hidden` para secretos/tokens/hash/password.
- Si se necesita control extra de salida, sobrescribir `toArray()`.

## 5) Model

- Extender `CodeIgniter\Model`.
- Usar traits:
  - `Filterable`, `Searchable`
  - `Auditable` cuando aplique auditoría.
- Configurar:
  - `$table`, `$primaryKey`, `$returnType`
  - `$useSoftDeletes`, `$useTimestamps`
  - `$allowedFields`
  - `$validationRules`
  - `$searchableFields`, `$filterableFields`, `$sortableFields`

## 6) Service Interface

- Crear `app/Interfaces/{Resource}ServiceInterface.php`.
- Exponer mínimo:
  - `index(array $data): array`
  - `show(array $data): array`
  - `store(array $data): array`
  - `update(array $data): array`
  - `destroy(array $data): array`

## 7) Service

- Inyectar model en constructor.
- Usar `App\Libraries\Query\QueryBuilder` para listados.
- Reusar `AppliesQueryOptions` y `ValidatesRequiredFields`.
- Responder siempre con `App\Libraries\ApiResponse`.
- Lanzar excepciones y dejar que `ApiController` las serialice.
- Para `index`, usar `resolvePagination` y devolver `ApiResponse::paginated(...)`.
- Para `show/update/destroy`, validar `id` con `validateRequiredId`.

## 8) Services config

- Registrar en `app/Config/Services.php`:
  - Método `{resource}Service(bool $getShared = true)`.
  - Retornar `static::getSharedInstance(...)` si aplica.
  - Construir servicio con dependencias.

## 9) Controller

- Crear `app/Controllers/Api/V1/{Resource}Controller.php`.
- Extender `App\Controllers\ApiController`.
- Definir `protected string $serviceName = '{resource}Service';`.
- CRUD estándar ya está heredado:
  - `index/show/create/update/delete`

## 10) Routes y seguridad

- Agregar rutas en `app/Config/Routes.php` dentro de `group('api/v1', ...)`.
- Aplicar filtros correctos:
  - `jwtauth` para autenticación.
  - `roleauth:admin` para write admin-only.
  - `throttle` o `authThrottle` en endpoints públicos.

## 11) Language + validación

- Añadir mensajes en `app/Language/en/{ResourcePlural}.php`.
- Añadir paridad en `app/Language/es/{ResourcePlural}.php`.
- Usar `lang('...')` en servicios/excepciones.
- Si aplica validación por dominio/acción, usar helper `validateOrFail(...)`.

## 12) OpenAPI

- Crear atributos OpenAPI en `app/Documentation/{Domain}/`.
- Definir endpoints, schemas y request bodies.
- Regenerar spec: `php spark swagger:generate`.

## 13) Tests mínimos

- Unit (servicio):
  - Éxito y errores de `show/store/update/destroy`.
  - Assert de estructura ApiResponse.
- Integration (model):
  - Insert/find/update/delete
  - Validaciones model-level
  - Filtros/búsqueda si aplica
- Feature (controller):
  - Auth requerido (401)
  - Rol requerido (403), si aplica
  - Flujos CRUD end-to-end con status esperado

## 14) Definition of Done

- Rutas visibles en `php spark routes`.
- `php spark swagger:generate` sin errores.
- Tests de nuevas piezas en verde.
- `composer cs-check` y `composer phpstan` en verde.
- Respuestas siguen formato estándar (`status`, `data`, `meta`, `errors`).
