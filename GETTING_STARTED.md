# Getting Started with CI4 API Starter

Welcome! This guide will help you get up and running with the CI4 API Starter template in under 30 minutes.

## What is This?

This is a **production-ready REST API template** built on CodeIgniter 4 that follows enterprise-grade architectural patterns. Think of it as a solid foundation that saves you weeks of setup and lets you focus on building your business logic.

### Key Features

- 🔐 **JWT Authentication** - Secure token-based auth with refresh tokens
- 👥 **Role-Based Access** - Admin/user roles with middleware protection
- 🇬 **Google Auth** - Social login support
- 📧 **Email System** - Verification, password reset, queue support
- 📁 **File Management** - Multipart & Base64 uploads ([Docs](docs/tech/file-storage.md))
- ⚙️ **Queue System** - Background job processing ([Docs](docs/tech/QUEUE.md))
- 🔍 **Advanced Querying** - Filtering, searching, sorting, pagination
- ✅ **Comprehensive Test Suite** - Unit, integration, and feature tests ([Docs](docs/tech/TESTING_GUIDELINES.md))
- 📚 **OpenAPI Docs** - Auto-generated Swagger documentation

---

## Core Concepts

### The 4-Layer Architecture

This project follows a strict layered architecture. Every request flows through these layers:

```
HTTP Request
     ↓
┌──────────────┐
│  CONTROLLER  │  Collects request data, delegates to service, returns HTTP response
└──────────────┘
     ↓
┌──────────────┐
│   SERVICE    │  Business logic, validation, orchestration
└──────────────┘
     ↓
┌──────────────┐
│    MODEL     │  Database operations (query builder)
└──────────────┘
     ↓
┌──────────────┐
│   ENTITY     │  Data representation (casting, hiding sensitive fields)
└──────────────┘
     ↓
HTTP Response (JSON)
```

**Golden Rule:** Each layer has ONE responsibility:
- **Controllers** = HTTP handling (no business logic!)
- **Services** = Business logic (validation, orchestration)
- **Models** = Database operations (query builder only)
- **Entities** = Data representation (casting, serialization)

---

## Quick Setup (5 Minutes)

### Prerequisites
- PHP 8.1+ with extensions: mysqli, mbstring, intl, json
- MySQL 8.0+
- Composer 2.x

### Installation

```bash
# 1. Clone the repository (or use as GitHub template)
git clone <your-fork-url>
cd ci4-api-starter

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env

# 4. Generate security keys
# For JWT secret (use output in .env)
openssl rand -base64 64

# For encryption key (copy hex2bin value to .env)
php spark key:generate

# 5. Configure database in .env
# database.default.hostname = localhost
# database.default.database = ci4_api
# database.default.username = root
# database.default.password = your_password

# 6. Run migrations
php spark migrate

# 7. Bootstrap first superadmin (required once)
php spark users:bootstrap-superadmin --email superadmin@example.com --password 'StrongPass123!' --first-name Super --last-name Admin

# 7.1 (Optional) Seed 1000 fake users for load/filter/search tests
php spark db:seed UsersLoadTestSeeder

# Optional .env overrides for load-test seed:
# USERS_FAKE_COUNT = 1000
# USERS_FAKE_BATCH_SIZE = 250
# USERS_FAKE_RESET = true
# USERS_FAKE_EMAIL_PREFIX = loadtest.user
# USERS_FAKE_EMAIL_DOMAIN = example.test
# USERS_FAKE_PASSWORD = Passw0rd!123

# 8. Start development server
php spark serve
```

Your API is now running at `http://localhost:8080` 🎉

---

## Your First API Request

### Test the Health Endpoint

```bash
curl http://localhost:8080/health
```

**Response (example):**
```json
{
  "status": "healthy",
  "timestamp": "2026-02-17 01:23:45",
  "checks": {
    "database": {
      "status": "healthy",
      "response_time_ms": 4.12
    }
  }
}
```

### Register a User

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "password": "SecurePass123!"
  }'
```

**Response:**
```json
{
  "status": "success",
  "message": "Registration received. Please verify your email and wait for admin approval.",
  "data": {
    "user": {
      "id": 1,
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "role": "user"
    }
  }
}
```

### Login

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

After approval, log in to obtain `access_token` and `refresh_token` for protected endpoints.

### Refresh Access Token

```bash
curl -X POST http://localhost:8080/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "YOUR_REFRESH_TOKEN"
  }'
```

### Access Protected Endpoint

```bash
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## Account Status and Auth Flow (Important)

- Self-registration (`POST /api/v1/auth/register`) creates users with `status = pending_approval`.
- Admin-created users (`POST /api/v1/users`) are created as `invited` and receive an invitation email to set password.
- Login and refresh only work for users with `status = active`.
- Email verification is enforced when `AUTH_REQUIRE_EMAIL_VERIFICATION = true`.
- Email verification endpoint accepts both:
  - `GET /api/v1/auth/verify-email?token=...`
  - `POST /api/v1/auth/verify-email` with `token` in body/form.

---

## Your First CRUD: Product Management

Let's build a complete Product resource step by step. This will teach you the patterns used throughout the project.

### Step 1: Scaffold the Resource First

```bash
php spark make:crud Product --domain Catalog --route products
```

This generates the CRUD skeleton (controller/service/interface/DTO/model/entity/docs/i18n/tests).

### Step 2: Validate Generated Bootstrap

```bash
php spark module:check Product --domain Catalog
```

This validates scaffolded artifacts and basic wiring. It does not validate migrations.

### Step 3: Create Migration(s)

```bash
php spark make:migration CreateProductsTable
php spark migrate
```

Then align model/entity fields with the real schema.

### Step 4: Finalize DTOs and Service

1. Complete request DTO `rules()`, `map()`, and `toArray()`.
2. Complete response DTO `fromArray()` and OpenAPI attributes.
3. Keep service pure:
   - reads return DTOs
   - command flows return `OperationResult`
   - no HTTP response building in services

### Step 5: Repository Decision

1. Start with `GenericRepository` for standard CRUD.
2. Create dedicated repository interface/implementation only for non-trivial domain queries.

### Step 6: Complete Routing, Docs, and Tests

1. Verify routes/filters in `app/Config/Routes.php`.
2. Complete endpoint docs in `app/Documentation/{Domain}/...`.
3. Complete Unit/Integration/Feature tests.
4. Run `composer quality` and `php spark swagger:generate`.

For the full canonical checklist, use:
- `docs/template/CRUD_FROM_ZERO.md`
- `docs/template/CRUD_FROM_ZERO.es.md`

---

## Understanding the Pattern

What you just built follows these patterns:

1. **Request flows through layers**: Controller → RequestDTO → Service → Repository/Model → Entity → ResponseDTO
2. **Controller is thin**: Only handles HTTP, delegates to service
3. **Service contains business logic**: Validation, error handling, orchestration
4. **Model handles database**: Query builder operations only
5. **Entity represents data**: Type casting, computed properties
6. **Exceptions for errors**: Throw custom exceptions, controller handles them
7. **Centralized response normalization**: `ApiController` + `ApiResponse::fromResult()` keep JSON responses consistent

This same pattern applies to **every resource** in the project.

---

## Next Steps

### 📚 Learn More

**Beginner → Intermediate:**
1. Read [`docs/architecture/OVERVIEW.md`](docs/architecture/OVERVIEW.md) - Understand the big picture
2. Read [`docs/architecture/LAYERS.md`](docs/architecture/LAYERS.md) - Deep dive into each layer
3. Read [`docs/architecture/REQUEST_FLOW.md`](docs/architecture/REQUEST_FLOW.md) - See the complete flow

**Intermediate → Advanced:**
4. Read [`docs/architecture/AUTHENTICATION.md`](docs/architecture/AUTHENTICATION.md) - JWT auth system
5. Read [`docs/architecture/QUERIES.md`](docs/architecture/QUERIES.md) - Advanced filtering/search
6. Read [`docs/architecture/EXTENSION_GUIDE.md`](docs/architecture/EXTENSION_GUIDE.md) - Extend the system

**Full documentation roadmap:** See [`docs/architecture/README.md`](docs/architecture/README.md)

**En español:**
- Ver [`docs/architecture/README.es.md`](docs/architecture/README.es.md) para roadmap completo
- Todos los documentos de arquitectura disponibles en español (sufijo `.es.md`)

### 🧪 Run Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suites
vendor/bin/phpunit tests/Unit         # Fast, no DB
vendor/bin/phpunit tests/Integration  # With DB
vendor/bin/phpunit tests/Feature      # HTTP tests
```

### 📖 Generate API Documentation

```bash
php spark swagger:generate
```

View at: `http://localhost:8080/docs/`
Raw spec: `http://localhost:8080/swagger.json`

### 🚀 Deploy

See deployment guides:
- Docker: `docker-compose up -d`
- Production: Configure `.env` for production, set up SSL, configure reverse proxy

---

## Troubleshooting

### Database Connection Failed
- Check `.env` database credentials
- Ensure MySQL is running
- Test connection: `php spark db:table users`

### JWT Token Invalid
- Regenerate JWT secret: `openssl rand -base64 64`
- Update `JWT_SECRET_KEY` in `.env`
- Clear cache: `rm -rf writable/cache/*`

### Tests Failing
- Check `phpunit.xml` database configuration
- Ensure test database exists: `ci4_test`
- Run migrations on test DB: `php spark migrate --env=testing`

### 404 on Routes
- Check `app/Config/Routes.php`
- List routes: `php spark routes`
- Verify controller namespace and class name

---

## Getting Help

- **Documentation**: [`docs/`](docs/) directory
- **Issues**: [GitHub Issues](https://github.com/david-cardenas/ci4-api-starter/issues)
- **Discussions**: [GitHub Discussions](https://github.com/david-cardenas/ci4-api-starter/discussions)
- **CodeIgniter 4 Docs**: https://codeigniter.com/user_guide/

---

**You're all set!** 🎉

You now understand the core architecture and have built your first CRUD resource. The same pattern applies to every resource in the system. Happy coding!
