# CRUD From Zero (Template Playbook)

Canonical guide for creating a new CRUD resource in this template.

This flow is **recommended by default**:
1. Scaffold first with `php spark make:crud ...`
2. Customize generated files
3. Add migration and persistence details
4. Close tests/docs/quality gates

Manual CRUD creation is still valid when custom structure is required.

## 1. Pre-Checklist

1. Define resource name (singular): `Product`
2. Define domain folder: `Catalog`
3. Define route slug (plural kebab): `products`
4. Define access model (public read, admin write, etc.)
5. Define minimum table schema and audit requirements

## 2. Scaffold First

The `make:crud` command handles the creation of all layers (Migration, Entity, Model, DTOs, Service, Controller).

### Option A: Interactive Mode (Recommended)
Run the command with just the resource and domain. The system will prompt you for each field's details.

```bash
php spark make:crud Product --domain Catalog
```

### Option B: CLI Mode (Fast)
Define your schema in a single string using the `--fields` option.

```bash
php spark make:crud Product --domain Catalog --fields="name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required"
```

**Field Syntax Guide:**
- Format: `name:type:options`
- Types: `string`, `text`, `int`, `bool`, `decimal`, `email`, `date`, `datetime`, `fk`, `json`.
- Options (pipe separated): `required`, `nullable`, `searchable`, `filterable`, `fk:table_name`.

What the scaffold generates:
1. Database migration files
2. Controller, Service, Interface
3. Request/Response DTOs
4. Model/Entity
5. OpenAPI endpoint placeholder file (`app/Documentation/...`)
6. i18n files (`en` and `es`)
7. Unit/Integration/Feature test skeletons
8. Service registration snippet in `app/Config/Services.php` (if missing)

What it does **not** generate:
1. Domain-specific repository interface/implementation
2. Final business rules and validation specifics

## 3. Validate Bootstrap Output

```bash
php spark module:check Product --domain Catalog
```

`module:check` validates generated module artifacts and basic wiring (`Services.php`, routes reference), but it does **not** validate migration existence/content.

## 4. Run Migration(s)

Since the scaffold generates the migration automatically, you only need to review it and apply it:

```bash
php spark migrate
```

Then implement:
1. Review final columns and constraints
2. Add required indexes if necessary
3. Ensure soft deletes are configured as desired

## 5. Align Persistence Layer

1. Update `Entity` casts/dates to match migration
2. Update `Model`:
   - `allowedFields`
   - `validationRules`
   - `searchableFields`, `filterableFields`, `sortableFields`
   - traits (`Filterable`, `Searchable`, `Auditable`) as needed

## 6. Finalize DTO Contracts

1. Request DTOs extend `BaseRequestDTO` and remain `readonly`
2. Implement complete `rules()`, `map()`, `toArray()`
3. Response DTO includes OpenAPI `#[OA\Property]` attributes and `fromArray()`
4. Keep DTO fields strictly aligned with API contract (no leaked internal fields)

## 7. Service + Repository Strategy

Service rules:
1. Service remains pure (no HTTP response building)
2. Read flows return DTOs; command flows return `OperationResult`
3. Use transactions for write operations

Repository default:
1. Use `GenericRepository` via `RepositoryInterface` for standard CRUD
2. Create dedicated `*RepositoryInterface` + implementation only when:
   - domain-specific queries are non-trivial
   - persistence rules are reused across services
   - custom query methods are required for clarity/testability

## 8. Register Dependencies

1. Ensure service registration exists in `app/Config/Services.php`
2. If using dedicated repository, register repository factory methods too
3. Keep constructor DI typed by interfaces where applicable

## 9. Controller, Routes, OpenAPI, i18n

1. Controller extends `ApiController`
2. Resolve service in `resolveDefaultService()`
3. Use `handleRequest('method', RequestDTO::class)` pattern
4. Add/verify routes in `app/Config/Routes.php` with proper filters
5. Complete endpoint docs in `app/Documentation/{Domain}/...`
6. Keep language parity in `app/Language/en` and `app/Language/es`

## 10. Testing and Quality Gates

1. Complete Unit tests (service behavior/contracts)
2. Complete Integration tests (model/persistence behavior)
3. Complete Feature tests (HTTP status + JSON structure + authorization)
4. Run:

```bash
php spark tests:prepare-db
composer quality
php spark swagger:generate
```

## FAQ

### When should I run migrations?
After running `make:crud` and validating the generated module skeleton with `module:check`.

### Does `make:crud` create migrations?
Yes. It uses a single schema to synchronize database, DTOs, and services.

### When do I need a dedicated repository?
Only when generic CRUD criteria are insufficient and domain persistence queries become explicit/complex.

### Is `make:crud` mandatory?
It is the recommended default. Manual CRUD creation is allowed for custom requirements.
