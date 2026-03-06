# CodeIgniter 4 API Starter Kit 🚀

A production-ready REST API starter template with an advanced **Automated Scaffolding Engine**, strict DTO-first architecture, and comprehensive quality guardrails.

## Key Features

- **⚡ Zero-Error Scaffolding:** Generate 100% functional CRUD modules in seconds (DTOs, Services, Models, Migrations, OpenAPI).
- **🛡️ DTO-First Architecture:** Strict data validation and transfer using PHP 8.2 readonly classes.
- **🔌 Smart Wiring:** Automatic service registration in `Config/Services.php` and domain traits.
- **📜 OpenAPI 3.0 Documentation:** Automatically generated and synchronized documentation.
- **✅ Built-in Quality:** Git pre-commit hooks (PHPStan, CS-Fixer, i18n) and comprehensive test suites.
- **🗃️ Advanced Patterns:** Generic Repository, Filterable/Searchable traits, and Audit Trail.

## Getting Started

1. **One-command Bootstrap (recommended):**
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/dcardenasl/ci4-api-starter/main/install.sh)"
   ```
   *This interactive installer clones the template, configures `.env`, creates DBs, runs migrations, and bootstraps the first superadmin.*
   *Existing local scripts (`init.sh`, `setup-env.sh`) remain available for internal/advanced workflows.*

2. **Manual Clone and Install (advanced/local):**
   ```bash
   git clone https://github.com/dcardenasl/ci4-api-starter.git
   cd ci4-api-starter
   composer install
   ```
   *Note: This automatically installs the Git pre-commit hooks.*

3. **Environment Setup:**
   ```bash
   cp .env.example .env
   # Update your DB and JWT_SECRET_KEY
   ```

4. **Migrate and Bootstrap Superadmin:**
   ```bash
   php spark migrate
   php spark users:bootstrap-superadmin --email superadmin@example.com --password 'StrongPass123!' --first-name Super --last-name Admin
   ```

## Development Workflow

### Generate a new Module
To create a complete CRUD resource with validation and documentation:

```bash
php spark make:crud Product --domain Catalog --fields="name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required"
```

**Next Steps:**
1. Run `php spark migrate` to apply the new table.
2. Run `php spark swagger:generate` to update the API documentation.
3. Start the server: `php spark serve`.

## Quality Enforcement
This project enforces high standards. Every commit runs:
- **PHP CS Fixer:** For code style consistency.
- **PHPStan:** For static analysis and type safety.
- **i18n-check:** To prevent hardcoded strings in DTOs/Services.

To run the full quality suite manually:
```bash
composer quality
```

## Documentation
- [Architecture Overview](ARCHITECTURE.md)
- [API Documentation](public/docs/index.html) (After generating swagger)
- [Getting Started Guide](GETTING_STARTED.md)
