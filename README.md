# CodeIgniter 4 API Starter Kit

![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-49%20passed-success)
![License](https://img.shields.io/badge/license-MIT-blue)

A production-ready REST API starter template for CodeIgniter 4 with JWT authentication, modular OpenAPI documentation, and clean layered architecture.

**Perfect for:** Starting new API projects, building microservices, or learning modern PHP API development.

## ✨ Features

- 🔐 **JWT Authentication** - Secure token-based auth with refresh capability
- 📚 **Modular OpenAPI Documentation** - Schema-based docs, 60% less boilerplate
- 🏗️ **Clean Architecture** - Controller → Service → Repository → Entity pattern
- 🎯 **ApiController Base** - Automatic request handling, 62% less code
- ✅ **49 Passing Tests** - Complete test coverage with PHPUnit
- 🚀 **CI/CD Ready** - GitHub Actions configured for PHP 8.1, 8.2, 8.3
- 🔒 **Secure by Default** - Bcrypt hashing, input validation, CSRF protection
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
POST /api/v1/auth/register  # Register new user
POST /api/v1/auth/login     # Login (returns JWT)
GET  /api/v1/auth/me        # Get current user (protected)
```

### Users (Protected - Requires JWT)
```bash
GET    /api/v1/users        # List all users
GET    /api/v1/users/{id}   # Get user by ID
POST   /api/v1/users        # Create user
PUT    /api/v1/users/{id}   # Update user
DELETE /api/v1/users/{id}   # Delete user (soft)
```

### Example Usage

**Register:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"john","email":"john@example.com","password":"Pass123!"}'
```

**Use protected endpoint:**
```bash
TOKEN="your-jwt-token-here"
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer $TOKEN"
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
│       └── UserController.php      # User CRUD
├── Documentation/                  # 🆕 Modular OpenAPI schemas
│   ├── Schemas/
│   │   ├── UserSchema.php          # Reusable User model (used 7x)
│   │   └── AuthTokenSchema.php     # JWT response structure
│   ├── Responses/
│   │   ├── UnauthorizedResponse.php
│   │   └── ValidationErrorResponse.php
│   └── RequestBodies/
│       ├── LoginRequest.php
│       ├── RegisterRequest.php
│       ├── CreateUserRequest.php
│       └── UpdateUserRequest.php
├── Services/
│   ├── JwtService.php              # JWT operations
│   └── UserService.php             # User business logic
├── Models/
│   └── UserModel.php               # Database operations
└── Entities/
    └── UserEntity.php              # Data model
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
- ✅ Bcrypt password hashing
- ✅ Passwords never exposed in responses
- ✅ Token expiration (1 hour, configurable)
- ✅ Input validation at model layer
- ✅ SQL injection protection (query builder)
- ✅ CSRF protection available
- ✅ Soft deletes for data recovery

**Important:** Before production:
1. Change `JWT_SECRET_KEY` to a strong random value
2. Use HTTPS only
3. Review [SECURITY.md](SECURITY.md) for complete checklist

## 🧪 Testing

Run the complete test suite:

```bash
vendor/bin/phpunit           # All 49 tests
vendor/bin/phpunit --testdox # Human-readable output
```

**Test Coverage:**
- ✅ 49 tests, 166 assertions
- ✅ Controllers (API endpoints)
- ✅ Services (business logic)
- ✅ Models (database operations)
- ✅ JWT authentication flow

CI automatically runs tests on PHP 8.1, 8.2, and 8.3.

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
- `codeigniter4/framework` ^4.5
- `firebase/php-jwt` ^7.0 - JWT handling
- `zircote/swagger-php` ^6.0 - OpenAPI generation

### Dev Dependencies
- `phpunit/phpunit` - Testing
- `fakerphp/faker` - Test data
- Docker configuration

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
