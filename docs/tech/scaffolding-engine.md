# ⚡ Scaffolding Engine (Zero-Error CRUD)

The Scaffolding Engine is the core productivity tool of this starter kit. It automates the creation of 100% functional, layered CRUD modules, ensuring that **Database**, **DTOs**, **Services**, and **OpenAPI** are always synchronized.

## 🏗️ The Modular Architecture

Instead of a single "template" command, our engine uses specialized generators coordinated by an **Orchestrator**:

1.  **DtoGenerator**: Creates Request (Index, Create, Update) and Response DTOs with PHP 8.2 readonly types and Swagger annotations.
2.  **MigrationGenerator**: Produces CI4 migrations with correct DB types, constraints, and Foreign Keys.
3.  **ModelEntityGenerator**: Sets up the Entity (`$casts`) and Model (`$allowedFields`, `$searchableFields`, `$filterableFields`).
4.  **ServiceGenerator**: Generates the Service Interface and the Business Logic layer.
5.  **ControllerGenerator**: Emits a thin controller (each CRUD action explicit, delegating to `handleRequest()`) plus a co-located OpenAPI endpoint class in `app/Documentation/{Domain}/`.

## 🧠 Smart Wiring (ConfigWireman)

The engine automatically "plugs" the new module into the system:
- **Domain Trait:** Creates `{Domain}DomainServices.php` if it doesn't exist.
- **Service Registration:** Injects the new Service and its Mapper into the domain trait.
- **Main Services:** Registers the new domain trait in `Config/Services.php` via `use` and `require_once`.

## 🧬 Type Mapping (The Brain)

We use a unified **TypeMapper** to ensure consistency. For example, a `decimal` field definition results in:
- **DB:** `DECIMAL(10,2)`
- **PHP:** `float`
- **Validation:** `required|decimal`
- **OpenAPI:** `type: "number", format: "float"`

## 🛡️ Safety Guardrails

- **Conflict Detection:** The orchestrator checks if any of the ~10 files already exist. If it finds a conflict, **nothing is written**, and a detailed error is reported.
- **Syntax Verification:** Every generation is automatically verified using PHP Lint (`php -l`) via a specialized Smoke Test.

## 🛠️ How to Use (Usage Guide)

Two entry points — pick the one that matches your environment:

### 1. `bin/make-crud.sh` (recommended, shell-safe)

Preferred default. Wraps `php spark make:crud`, quotes pipes correctly, auto-runs `composer cs-fix`, and prints the exact follow-up commands.

```bash
bash bin/make-crud.sh Product Catalog \
  'name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required' \
  yes
```

Signature: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Use this in: CI pipelines, Claude Code / AI assistants, shell scripts, and any non-TTY context.

### 2. `php spark make:crud` (interactive)

When you want the engine to prompt you for each field and its modifiers:

```bash
php spark make:crud Client --domain Sales
```

Or the explicit `--fields` variant (quote carefully with single quotes so the shell doesn't eat the pipes):

```bash
php spark make:crud Product --domain Catalog --fields='name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required'
```

> ⚠️ In non-TTY environments `--fields` can silently lose pipe-separated modifiers, and the engine falls back to interactive mode — which then hangs forever waiting for input. That is the exact reason `bin/make-crud.sh` exists.

## 🧬 Detailed Field Syntax (`--fields`)

When using CLI mode, the fields string follows this format:
`name:type:options,name2:type2:options2`

### Supported Types
- `string`: Standard VARCHAR(255).
- `text`: Long TEXT field.
- `int`: INTEGER.
- `bool`: BOOLEAN (TINYINT 1).
- `decimal`: DECIMAL(10,2) mapped to float.
- `email`: VARCHAR(255) with email validation.
- `date`: DATE.
- `datetime`: DATETIME.
- `fk`: Foreign Key (BigInt Unsigned). Requires table name in options.
- `json`: JSON field for structured data.

### Field Options (Separated by `|`)
- `required`: Field must be present and not empty.
- `nullable`: Explicitly allow NULL values.
- `searchable`: Enables partial string matching (`LIKE %query%`) in the Index endpoint.
- `filterable`: Enables exact match filtering in the Index endpoint.
- `fk:table_name`: **(Required for `fk` type)** Specifies the related database table.

## 🚀 Post-Scaffolding Workflow

After running `make:crud`, always follow these three steps to finalize your module:

1.  **Verify Registration**: Run `php spark module:check {Resource} --domain {Domain}`. This ensures the service and domain traits are correctly wired into `Config/Services.php`.
2.  **Apply Database Changes**: Run `php spark migrate`. The scaffold generates a migration file in `app/Database/Migrations/`.
3.  **Synchronize Documentation**: Run `php spark swagger:generate`. This reads the new DTOs and Documentation files to update `public/swagger.json`.

Your new API endpoints will be immediately available at `/api/v1/{route}`.
