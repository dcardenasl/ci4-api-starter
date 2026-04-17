# CRUD From Zero (Template Playbook)

Canonical guide for creating a new CRUD resource in this template.

This flow is **recommended by default**:
1. Scaffold first with `bin/make-crud.sh` (or `php spark make:crud` for interactive)
2. Validate wiring with `module:check`
3. Apply migration, restart server, regenerate OpenAPI
4. Customize only what business rules require
5. Close tests and quality gates

Manual CRUD creation is still valid when custom structure is required.

## 1. Pre-Checklist

1. Define resource name (singular, StudlyCase): `Product`
2. Define domain folder (StudlyCase): `Catalog`
3. Define route slug (plural kebab, optional): `products`
4. Define access model (public read, admin write, etc.)
5. Define minimum table schema and audit requirements
6. Decide soft-delete vs. hard-delete (see §2.3)

## 2. Scaffold First

The `make:crud` engine generates all layers in one pass (Migration, Entity, Model, Request/Response DTOs, Service, Interface, Controller, OpenAPI doc class, i18n files, test skeletons, service wiring, and the route file).

### 2.1 Recommended: `bin/make-crud.sh` (shell-safe)

```bash
bash bin/make-crud.sh Product Catalog \
  'name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required' \
  yes
```

Signature: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Why prefer the wrapper:
- Single-quote handling prevents shells from eating the `|` inside `--fields`
- Runs `composer cs-fix` automatically post-generation (keeps pre-commit hooks happy)
- Prints the exact follow-up commands with placeholders resolved
- Safe in non-TTY environments (CI pipelines, Claude Code, automation scripts)

### 2.2 Alternative: `php spark make:crud` (interactive)

When you want the engine to prompt you for each field interactively:

```bash
php spark make:crud Product --domain Catalog
```

The `--fields` variant works too, but must be quoted carefully:

```bash
php spark make:crud Product --domain Catalog --fields='name:string:required|searchable,price:decimal:required|filterable'
```

> If you run `make:crud` in a non-interactive environment and forget to quote the pipes, the command silently drops all field flags and waits for interactive input — which never arrives. This is the #1 reason `bin/make-crud.sh` is preferred.

### 2.3 The `SoftDelete` flag

- **`yes` (default)** — Adds a `deleted_at TIMESTAMP NULL` column, sets `useSoftDeletes = true` in the Model, and includes `deleted_at` in the Entity `$dates`. Use for business entities (Users, Orders, Products) where you need an audit trail or restore capability.
- **`no`** — No `deleted_at` column; deletes are physical. Use for lookup tables (Permissions, Roles, Statuses) and append-only tables (AuditLog).

### 2.4 Field Syntax

Format: `name:type:modifier1|modifier2|modifier3`

Multiple fields separated by commas. Always wrap the whole string in **single quotes** when using pipes.

**Supported Types:**

| Type | Database | PHP | Example |
|------|----------|-----|---------|
| `string` | `VARCHAR(255)` | `string` | `name:string:required` |
| `text` | `TEXT` | `string` | `description:text:nullable` |
| `int` | `INT UNSIGNED` | `int` | `stock:int:required` |
| `decimal` | `DECIMAL(10,2)` | `float` | `price:decimal:required` |
| `bool` | `TINYINT` | `bool` | `is_active:bool` |
| `email` | `VARCHAR(255)` | `string` | `email:email:required` |
| `date` | `DATE` | `string` | `birth_date:date:nullable` |
| `datetime` | `DATETIME` | `string` | `published_at:datetime` |
| `json` | `JSON` | `array` | `metadata:json:nullable` |
| `fk:table` | `INT + FK` | `int` | `category_id:fk:categories:required` |

**Supported Modifiers:**

| Modifier | Effect |
|----------|--------|
| `required` | `NOT NULL` in DB + `required` validation rule |
| `nullable` | `NULL` allowed in DB + `permit_empty` validation rule |
| `searchable` | Field is included in the `?search=` query (LIKE) |
| `filterable` | Field is included in the `?filter[...]=` query (exact match) |
| `fk:table` | Foreign key to `table.id` + `is_not_unique[table.id]` validation |

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
