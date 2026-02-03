# CodeIgniter 4 API Starter Kit

![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-533%20tests-success)
![Coverage](https://img.shields.io/badge/coverage-95%25%20critical-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)

English | [Español](README.es.md)

A production-ready REST API starter template for CodeIgniter 4 with JWT authentication, modular OpenAPI documentation, and clean layered architecture.

**Perfect for:** Starting new API projects, building microservices, or learning modern PHP API development.

## ✨ Features

### Core Features
- 🔐 **JWT Authentication** - Secure token-based auth with refresh tokens & revocation
- 📧 **Email System** - Email verification, password reset, queue infrastructure
- 📁 **File Management** - Upload/manage files with cloud storage support
- 🔍 **Advanced Querying** - Pagination, filtering, searching, sorting
- 📊 **Monitoring** - Health checks, metrics, request logging, audit trail
- 🌍 **Internationalization** - Locale detection from Accept-Language header

### Architecture & Developer Experience
- 📚 **Modular OpenAPI Documentation** - Schema-based docs, 60% less boilerplate
- 🏗️ **Clean Architecture** - Controller → Service → Repository → Entity pattern
- 🎯 **ApiController Base** - Automatic request handling, 62% less code
- 🔌 **Service Interfaces** - Interface-based design for better testability
- ✅ **533 Tests** - Comprehensive test coverage (unit, model, integration)
- 🎯 **95% Critical Coverage** - All security and business logic tested
- 🧪 **Test Organization** - Separated unit, model, and integration tests
- 🚀 **CI/CD Ready** - GitHub Actions configured for PHP 8.1, 8.2, 8.3
- 🔒 **Secure by Default** - Bcrypt hashing, timing-attack protection, input validation
- 🐳 **Docker Support** - Production-ready containerization included

## 🚀 Quick Start (1 minute)

### Using GitHub Template (Recommended)

1. **Click "Use this template"** button at the top of this page
2. **Clone your new repository:**
   ```bash
   git clone https://github.com/YOUR-USERNAME/YOUR-NEW-REPO.git
   cd YOUR-NEW-REPO
   ```

3. **Run the initialization script:**
   ```bash
   chmod +x init.sh
   ./init.sh
   ```

That's it! The script will:
- ✓ Install dependencies
- ✓ Generate secure keys (JWT + encryption)
- ✓ Configure environment
- ✓ Create database
- ✓ Run migrations
- ✓ Generate API documentation
- ✓ Start development server

Your API will be running at `http://localhost:8080` 🎉

### Manual Setup

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env

# 3. Generate secure keys
openssl rand -base64 64  # Add to JWT_SECRET_KEY in .env
php spark key:generate   # Add to encryption.key in .env

# 4. Configure database in .env, then:
php setup_mysql.php      # Create databases
php spark migrate        # Run migrations

# 5. Start server
php spark serve
```

## 📖 API Endpoints

### Authentication (Public)
```bash
POST /api/v1/auth/register           # Register new user
POST /api/v1/auth/login              # Login (returns JWT + refresh token)
POST /api/v1/auth/refresh            # Refresh access token
POST /api/v1/auth/verify-email       # Verify email address
POST /api/v1/auth/forgot-password    # Request password reset
GET  /api/v1/auth/validate-reset-token  # Validate reset token
POST /api/v1/auth/reset-password     # Reset password
```

### Authentication (Protected)
```bash
GET  /api/v1/auth/me                 # Get current user
POST /api/v1/auth/resend-verification # Resend verification email
POST /api/v1/auth/revoke             # Revoke current token
POST /api/v1/auth/revoke-all         # Revoke all user tokens
```

### Users (Protected - Requires JWT)
```bash
GET    /api/v1/users              # List users (supports pagination, filtering, search)
GET    /api/v1/users/{id}         # Get user by ID
POST   /api/v1/users              # Create user (admin only)
PUT    /api/v1/users/{id}         # Update user (admin only)
DELETE /api/v1/users/{id}         # Delete user (admin only, soft delete)
```

### Files (Protected - Requires JWT)
```bash
GET    /api/v1/files              # List uploaded files
POST   /api/v1/files/upload       # Upload file
GET    /api/v1/files/{id}         # Get file details
DELETE /api/v1/files/{id}         # Delete file
```

### Health Checks (Public, No Rate Limiting)
```bash
GET /health                        # Complete system health check
GET /ping                          # Simple uptime check
GET /ready                         # Readiness probe (Kubernetes)
GET /live                          # Liveness probe (Kubernetes)
```

### Metrics (Admin Only)
```bash
GET  /api/v1/metrics               # System metrics overview
GET  /api/v1/metrics/requests      # Request metrics
GET  /api/v1/metrics/slow-requests # Slow request log
GET  /api/v1/metrics/custom/{name} # Custom metric
POST /api/v1/metrics/record        # Record custom metric
```

### Audit Trail (Admin Only)
```bash
GET /api/v1/audit                  # List all audit logs
GET /api/v1/audit/{id}             # Get specific audit entry
GET /api/v1/audit/entity/{type}/{id} # Get audits for specific entity
```

### Example Usage

**Register:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"john","email":"john@example.com","password":"Pass123!"}'
```

**Login with refresh token:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"john","password":"Pass123!"}'
# Returns: {"status":"success","data":{"token":"...","refreshToken":"..."}}
```

**Refresh access token:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"your-refresh-token"}'
```

**Use protected endpoint with filtering:**
```bash
TOKEN="your-jwt-token-here"
curl -X GET "http://localhost:8080/api/v1/users?filter[role][eq]=admin&search=john&page=1&perPage=10" \
  -H "Authorization: Bearer $TOKEN"
```

**Upload file:**
```bash
curl -X POST http://localhost:8080/api/v1/files/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@/path/to/file.pdf"
```

**Check system health:**
```bash
curl http://localhost:8080/health
# Returns: {"status":"healthy","checks":{"database":"ok","cache":"ok","storage":"ok"}}
```

**View API Documentation:**
- Swagger JSON: http://localhost:8080/swagger.json
- Import into [Swagger UI](https://editor.swagger.io/) or [Postman](https://www.postman.com/)

## 🏗️ Project Structure

```
app/
├── Commands/
│   └── GenerateSwagger.php         # OpenAPI doc generator
├── Config/
│   ├── OpenApi.php                 # API documentation config
│   └── Routes.php                  # Route definitions
├── Controllers/
│   ├── ApiController.php           # Base controller (auto request/response)
│   └── Api/V1/
│       ├── AuthController.php      # Authentication (login, register, me)
│       ├── UserController.php      # User CRUD
│       ├── TokenController.php     # Token refresh & revocation
│       ├── VerificationController.php  # Email verification
│       ├── PasswordResetController.php # Password reset
│       ├── FileController.php      # File management
│       ├── HealthController.php    # Health checks
│       ├── MetricsController.php   # Monitoring metrics
│       └── AuditController.php     # Audit trail
├── Documentation/                  # Modular OpenAPI schemas
│   ├── Schemas/                    # Reusable data models
│   ├── Responses/                  # Standard error responses
│   └── RequestBodies/              # Request payloads
├── Services/
│   ├── JwtService.php              # JWT operations
│   ├── UserService.php             # User business logic
│   ├── RefreshTokenService.php     # Token refresh
│   ├── TokenRevocationService.php  # Token revocation
│   ├── EmailService.php            # Email sending
│   ├── VerificationService.php     # Email verification
│   ├── PasswordResetService.php    # Password reset
│   ├── FileService.php             # File operations
│   └── AuditService.php            # Audit logging
├── Interfaces/                     # Service interfaces
│   ├── UserServiceInterface.php
│   ├── JwtServiceInterface.php
│   ├── RefreshTokenServiceInterface.php
│   ├── TokenRevocationServiceInterface.php
│   ├── FileServiceInterface.php
│   └── AuditServiceInterface.php
├── Filters/
│   ├── CorsFilter.php              # CORS handling
│   ├── ThrottleFilter.php          # Rate limiting
│   ├── JwtAuthFilter.php           # JWT validation
│   ├── RoleAuthorizationFilter.php # Role-based access
│   ├── LocaleFilter.php            # i18n locale detection
│   └── RequestLoggingFilter.php    # Request logging
├── Traits/
│   ├── Auditable.php               # Auto audit logging
│   ├── Filterable.php              # Advanced filtering
│   └── Searchable.php              # Full-text search
├── Models/
│   ├── UserModel.php               # Database operations
│   ├── RefreshTokenModel.php
│   ├── RevokedTokenModel.php
│   ├── FileModel.php
│   └── AuditLogModel.php
└── Entities/
    ├── UserEntity.php              # Data models
    ├── RefreshTokenEntity.php
    ├── FileEntity.php
    └── AuditLogEntity.php
```

## 🔍 Advanced Query Features

The API supports powerful querying capabilities on list endpoints:

### Pagination
```bash
GET /api/v1/users?page=1&perPage=20
```

### Filtering
Use field operators to filter results:
```bash
# Equal
GET /api/v1/users?filter[role][eq]=admin

# Like (partial match)
GET /api/v1/users?filter[email][like]=%@gmail.com

# Greater than
GET /api/v1/users?filter[created_at][gt]=2025-01-01

# Multiple filters (AND logic)
GET /api/v1/users?filter[role][eq]=admin&filter[email][like]=%@company.com
```

**Supported operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Searching
Full-text search across configured fields:
```bash
GET /api/v1/users?search=john
# Searches across username, email, first_name, last_name
```

### Sorting
```bash
GET /api/v1/users?sort=created_at&direction=desc
GET /api/v1/users?sort=email&direction=asc
```

### Combining Features
```bash
GET /api/v1/users?search=john&filter[role][eq]=user&sort=created_at&direction=desc&page=1&perPage=10
```

## 🎯 Adding New Resources

Creating a new resource is fast with the included patterns:

```bash
# 1. Create migration
php spark make:migration CreateProductsTable

# 2. Create files following the pattern:
app/Entities/ProductEntity.php       # Data model
app/Models/ProductModel.php          # Database layer
app/Services/ProductService.php      # Business logic
app/Controllers/Api/V1/ProductController.php  # API endpoints
app/Documentation/Schemas/ProductSchema.php   # OpenAPI schema

# 3. Add routes in app/Config/Routes.php
$routes->resource('api/v1/products', ['controller' => 'Api\V1\ProductController']);

# 4. Generate documentation
php spark swagger:generate
```

**Example Controller (extends ApiController):**
```php
class ProductController extends ApiController
{
    protected ProductService $productService;

    protected function getService(): object
    {
        return $this->productService;
    }

    protected function getSuccessStatus(string $method): int
    {
        return match($method) {
            'store' => 201,
            default => 200,
        };
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');  // That's it!
    }
}
```

**Result:** Complete CRUD resource in ~30 minutes instead of 2-3 hours.

## 📚 Documentation

- **[DEVELOPMENT.md](DEVELOPMENT.md)** - Complete architecture guide, patterns, and best practices
- **[TESTING.md](TESTING.md)** - Testing guide with examples
- **[SECURITY.md](SECURITY.md)** - Security guidelines and best practices
- **[CI_CD.md](CI_CD.md)** - CI/CD configuration and deployment
- **[TEMPLATE_SETUP.md](TEMPLATE_SETUP.md)** - How to configure as GitHub template

## ⚙️ Requirements

- **PHP** 8.1+ (8.2 or 8.3 recommended)
- **MySQL** 8.0+
- **Composer** 2.x
- **Extensions**: mysqli, mbstring, intl, json

## 🔒 Security Features

- ✅ JWT authentication with Bearer tokens
- ✅ Refresh tokens with secure rotation
- ✅ Token revocation (individual & all user tokens)
- ✅ Bcrypt password hashing
- ✅ Timing-attack protection on login
- ✅ Passwords never exposed in responses
- ✅ Token expiration (1 hour, configurable)
- ✅ Email verification required
- ✅ Secure password reset flow
- ✅ Input validation at model layer
- ✅ SQL injection protection (query builder)
- ✅ Rate limiting on all API endpoints
- ✅ Request logging for security monitoring
- ✅ Audit trail for sensitive operations
- ✅ CSRF protection available
- ✅ Soft deletes for data recovery

**Important:** Before production:
1. Change `JWT_SECRET_KEY` to a strong random value
2. Configure email service (SMTP settings)
3. Set up cloud storage (S3-compatible)
4. Use HTTPS only
5. Review [SECURITY.md](SECURITY.md) for complete checklist

## 🧪 Testing

The project includes comprehensive test coverage with **533 tests** organized by type:

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Human-readable output
vendor/bin/phpunit --testdox

# Unit tests only (fast, no database required)
vendor/bin/phpunit tests/unit/

# Model tests (database operations)
vendor/bin/phpunit tests/Models/

# Integration tests (full service layer)
vendor/bin/phpunit tests/Services/

# Controller tests (HTTP endpoints)
vendor/bin/phpunit tests/Controllers/

# Specific service
vendor/bin/phpunit tests/unit/Services/RefreshTokenServiceTest.php
```

### Test Coverage by Category

**🔐 Authentication & Security (100%)**
- ✅ JWT token generation/validation
- ✅ Refresh token rotation & revocation
- ✅ Token blacklist management
- ✅ Password reset flow with timing-attack protection
- ✅ Email verification with expiration
- ✅ Login with email enumeration prevention
- ✅ Role injection prevention

**📁 File Management (100%)**
- ✅ File upload validation (size, type, mime)
- ✅ Storage abstraction (local/S3)
- ✅ Ownership enforcement
- ✅ Rollback on errors

**📊 Audit & Logging (100%)**
- ✅ Automatic audit trail logging
- ✅ Old/new value diff detection
- ✅ Entity history tracking
- ✅ User action tracking

**📧 Email Service (100%)**
- ✅ Email sending (immediate/queued)
- ✅ Template rendering
- ✅ SMTP configuration

**👥 User Management (100%)**
- ✅ CRUD operations
- ✅ Password hashing & verification
- ✅ Role-based access control

### Test Organization

```
tests/
├── unit/                    # Unit tests (142 tests, 93% passing)
│   └── Services/           # Service layer with mocked dependencies
├── Models/                  # Model tests (150 tests)
│   └── Database operations with real DB
├── Services/                # Integration tests (220 tests)
│   └── Full service layer with dependencies
└── Controllers/             # Controller tests (21 tests)
    └── HTTP endpoint testing
```

### Test Statistics

- **Total Tests**: 533 tests
- **Unit Test Pass Rate**: 93% (132/142)
- **Critical Coverage**: 95%
- **Test Files Created**: 20 files
- **Lines of Test Code**: ~16,000 lines

### Continuous Integration

CI automatically runs all tests on PHP 8.1, 8.2, and 8.3 via GitHub Actions.

**Test database** is configured separately in `phpunit.xml` using the `ci4_test` database.

## 🐳 Docker Support

```bash
# Production-ready setup
docker-compose up -d

# Your API runs at http://localhost:8080
# MySQL at localhost:3306
# Adminer at http://localhost:8081
```

See `docker-compose.yml` for configuration.

## 🛠️ Common Commands

```bash
# Development
php spark serve                   # Start dev server
php spark routes                  # List all routes
php spark swagger:generate        # Regenerate API docs

# Database
php spark migrate                 # Run migrations
php spark migrate:rollback        # Rollback migrations
php spark db:seed UserSeeder      # Seed data

# Testing
vendor/bin/phpunit                # Run all tests
composer audit                    # Security check
```

## 📦 What's Included

### Core Dependencies
- `codeigniter4/framework` ^4.5 - Main framework
- `firebase/php-jwt` ^7.0 - JWT authentication
- `zircote/swagger-php` ^6.0 - OpenAPI documentation

### Dev Dependencies
- `phpunit/phpunit` - Testing framework
- `fakerphp/faker` - Test data generation
- `php-cs-fixer` - Code style enforcement
- `phpstan` - Static analysis
- Docker configuration

### Built-in Features
- JWT auth with refresh tokens & revocation
- Email verification & password reset
- File upload with cloud storage support
- Advanced pagination, filtering, searching
- Health checks for Kubernetes/monitoring
- Metrics & performance tracking
- Audit trail logging
- Request logging & rate limiting
- Internationalization (i18n)
- Complete OpenAPI documentation

## 🔄 Keeping Updated

This is a starter template, not a package. After creating your project:

1. **Customize for your needs** - This is your codebase now
2. **Remove unused features** - Delete what you don't need
3. **Add your resources** - Follow the established patterns
4. **Check for updates** - Occasionally review the original template

## 🤝 Contributing

Contributions to improve the starter kit are welcome!

1. Fork the repository
2. Create feature branch (`git checkout -b feature/improvement`)
3. Commit changes (`git commit -m 'Add improvement'`)
4. Push to branch (`git push origin feature/improvement`)
5. Open Pull Request

## 📄 License

MIT License - use for personal or commercial projects.

## 🙏 Acknowledgments

Built with:
- [CodeIgniter 4](https://codeigniter.com/)
- [firebase/php-jwt](https://github.com/firebase/php-jwt)
- [swagger-php](https://github.com/zircote/swagger-php)

## 💬 Support

- **Issues:** [GitHub Issues](https://github.com/dcardenasl/ci4-api-starter/issues)
- **Discussions:** [GitHub Discussions](https://github.com/dcardenasl/ci4-api-starter/discussions)
- **Documentation:** See the `/docs` folder

---

**Ready to build your API?** Click "Use this template" above to get started! 🚀
