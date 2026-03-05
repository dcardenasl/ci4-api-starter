# ⚡ Scaffolding Engine (Zero-Error CRUD)

The Scaffolding Engine is the core productivity tool of this starter kit. It automates the creation of 100% functional, layered CRUD modules, ensuring that **Database**, **DTOs**, **Services**, and **OpenAPI** are always synchronized.

## 🏗️ The Modular Architecture

Instead of a single "template" command, our engine uses specialized generators coordinated by an **Orchestrator**:

1.  **DtoGenerator**: Creates Request (Index, Create, Update) and Response DTOs with PHP 8.2 readonly types and Swagger annotations.
2.  **MigrationGenerator**: Produces CI4 migrations with correct DB types, constraints, and Foreign Keys.
3.  **ModelEntityGenerator**: Sets up the Entity (`$casts`) and Model (`$allowedFields`, `$searchableFields`, `$filterableFields`).
4.  **ServiceGenerator**: Generates the Service Interface and the Business Logic layer.
5.  **ControllerGenerator**: Orchestrates everything with the `HasCrudActions` trait and OpenAPI endpoint documentation.

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

## 🛠️ Usage Examples

### 1. Interactive Mode (Best for humans)
```bash
php spark make:crud Client --domain Sales
```
*The command will ask you for each field name, type, and options.*

### 2. CLI Mode (Best for automation)
```bash
php spark make:crud Product --fields="name:string:required|searchable,price:decimal:required,category_id:fk:categories"
```
*Options like `searchable`, `filterable`, `required`, or `nullable` can be combined.*
