# CI4 API Starter

![CI Status](https://img.shields.io/badge/CI-passing-brightgreen)
![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-blue)
![Tests](https://img.shields.io/badge/tests-49%20passed-success)
![License](https://img.shields.io/badge/license-MIT-blue)

A production-ready CodeIgniter 4 REST API starter project with JWT authentication, layered architecture, and comprehensive OpenAPI documentation.

## Features

- 🔐 **JWT Authentication** - Secure token-based authentication with Bearer tokens
- 📚 **OpenAPI/Swagger Documentation** - Auto-generated API documentation
- 🏗️ **Clean Layered Architecture** - Controller → Service → Model → Entity pattern
- 🎯 **RESTful API** - Standardized JSON responses with proper HTTP status codes
- 🔒 **Secure by Default** - Password hashing, token validation, input sanitization
- 🗄️ **MySQL Database** - Migrations, seeders, and soft deletes
- ✅ **Comprehensive Testing** - 49 PHPUnit tests covering all layers
- 🚀 **GitHub Actions CI/CD** - Automated testing on push
- ♻️ **CRUD Operations** - Complete user management with authentication
- ⚙️ **Environment-based Configuration** - Easy deployment across environments
- 🐳 **Docker Support** - Production-ready containerization

## Quick Start

Get your API running in minutes:

### Automated Setup (Recommended)

Use the initialization script to set up everything automatically:

```bash
# 1. Clone the repository
git clone <repository-url>
cd ci4-api-starter

# 2. Run the init script
./init.sh
```

The script will:
- ✓ Check requirements (PHP, MySQL, Composer, OpenSSL)
- ✓ Install dependencies
- ✓ Configure environment (.env)
- ✓ Generate secure keys (JWT & encryption)
- ✓ Create databases
- ✓ Run migrations
- ✓ Create initial admin user (optional)
- ✓ Generate API documentation
- ✓ Start development server

Your API will be running at `http://localhost:8080` in under 10 minutes!

### Manual Setup

If you prefer manual setup or need more control:

```bash
# 1. Clone and setup
git clone <repository-url>
cd ci4-api-starter
composer install
cp .env.example .env

# 2. Generate secure keys
openssl rand -base64 64  # Copy to JWT_SECRET_KEY in .env
php spark key:generate   # Copy to encryption.key in .env

# 3. Configure database in .env
# Edit database credentials to match your local MySQL

# 4. Setup database
php setup_mysql.php      # Or create databases manually
php spark migrate

# 5. Start the server
php spark serve

# 6. Test the API
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","email":"admin@example.com","password":"secure123"}'
```

### Init Script Options

```bash
./init.sh                 # Full setup (interactive)
./init.sh --skip-deps     # Skip composer install
./init.sh --skip-db       # Skip database creation
```

## Using as a Starter Template

This project is designed to be forked and customized for your own API projects.

### Setup Your New Project

1. **Fork or clone this repository:**
   ```bash
   git clone <repository-url> my-new-api
   cd my-new-api
   rm -rf .git  # Remove git history to start fresh
   git init     # Initialize new repository
   ```

2. **Customize project identity:**
   - Update `composer.json` with your project name and details
   - Update `README.md` with your project information
   - Update `app/Config/OpenApi.php` with your API details

3. **Generate new security keys:**
   ```bash
   openssl rand -base64 64  # New JWT secret
   php spark key:generate    # New encryption key
   ```

4. **Customize the User resource (optional):**
   - Add fields to `app/Entities/UserEntity.php`
   - Create migration: `php spark make:migration AddFieldsToUsers`
   - Update `app/Models/UserModel.php` validation rules
   - Update `app/Services/UserService.php` business logic

5. **Add your own resources:**
   - Follow the architecture pattern (see "Creating New Resources" below)
   - Each resource gets: Entity, Model, Service, Controller
   - Use `ApiController` base for reduced boilerplate

### Customization Checklist

- [ ] Update `composer.json` (name, description, authors)
- [ ] Update `README.md` with your project details
- [ ] Configure `.env` with secure keys and credentials
- [ ] Update `app/Config/OpenApi.php` API metadata
- [ ] Customize or remove example User resource
- [ ] Add your domain models and resources
- [ ] Update tests for your use case
- [ ] Configure CI/CD for your repository
- [ ] Review and update security settings

## Requirements

- **PHP** 8.1 or higher
- **MySQL** 8.0 or higher
- **Composer** 2.x
- **PHP Extensions**:
  - mysqli (database)
  - mbstring (string handling)
  - intl (internationalization)
  - json (JSON parsing)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd ci4-api-starter
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
# Copy the example file
cp .env.example .env
```

**⚠️ IMPORTANT SECURITY NOTICE:**
- The `.env` file is ignored by git and should NEVER be committed
- Generate secure keys before running the application
- See [SECURITY.md](SECURITY.md) for detailed instructions

Edit `.env` and configure your settings:
```bash
# Environment
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080'

# Database (use your local MySQL credentials)
database.default.hostname = 127.0.0.1
database.default.database = ci4_api
database.default.username = root
database.default.password = YOUR_DATABASE_PASSWORD_HERE
database.default.DBDriver = MySQLi

# JWT Authentication (GENERATE SECURE KEY!)
JWT_SECRET_KEY = ''  # Generate with: openssl rand -base64 64

# Encryption Key (GENERATE SECURE KEY!)
encryption.key = ''  # Generate with: php spark key:generate
```

**Generate secure keys:**
```bash
# Generate JWT secret
openssl rand -base64 64

# Generate encryption key
php spark key:generate
```

> ⚠️ **Critical**: Never commit `.env` to git! It contains sensitive credentials.

4. Create the database:
```bash
php setup_mysql.php
```

Or manually:
```sql
CREATE DATABASE ci4_api;
CREATE DATABASE ci4_test;
```

5. Run migrations:
```bash
php spark migrate
```

6. (Optional) Seed initial users:
```bash
# First, customize app/Database/Seeds/UserSeeder.php with your initial users
# Then run:
php spark db:seed UserSeeder
```

> Note: The UserSeeder is provided as a template. Uncomment and customize the example code to seed your initial admin users.

## Usage

Start the development server:
```bash
php spark serve
```

The API will be available at `http://localhost:8080`

## API Documentation

### OpenAPI/Swagger

View the complete API documentation at:
- **Swagger JSON**: http://localhost:8080/swagger.json
- Import this file into [Swagger UI](https://swagger.io/tools/swagger-ui/) or [Postman](https://www.postman.com/) for interactive documentation

To regenerate the documentation after making changes:
```bash
php spark swagger:generate
```

## API Endpoints

### Authentication (Public)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/v1/auth/register` | Register new user | No |
| POST | `/api/v1/auth/login` | Login and get JWT token | No |
| GET | `/api/v1/auth/me` | Get current user info | Yes |

### Users (Protected)

All user endpoints require JWT authentication via `Authorization: Bearer {token}` header.

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/users` | Get all users | Yes |
| GET | `/api/v1/users/{id}` | Get user by ID | Yes |
| POST | `/api/v1/users` | Create new user | Yes |
| PUT | `/api/v1/users/{id}` | Update user | Yes |
| DELETE | `/api/v1/users/{id}` | Delete user (soft) | Yes |

### Example Requests

#### Authentication

**Register a new user:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "securepass123"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": "1",
      "username": "johndoe",
      "email": "john@example.com",
      "role": "user"
    }
  }
}
```

**Login:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "johndoe",
    "password": "securepass123"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": "1",
      "username": "johndoe",
      "email": "john@example.com",
      "role": "user"
    }
  }
}
```

**Get current user (authenticated):**
```bash
TOKEN="your-jwt-token-here"
curl -X GET http://localhost:8080/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

#### User Management (Protected)

**Get all users:**
```bash
TOKEN="your-jwt-token-here"
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer $TOKEN"
```

**Get user by ID:**
```bash
TOKEN="your-jwt-token-here"
curl -X GET http://localhost:8080/api/v1/users/1 \
  -H "Authorization: Bearer $TOKEN"
```

**Create a new user:**
```bash
TOKEN="your-jwt-token-here"
curl -X POST http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"username":"jane_doe","email":"jane@example.com"}'
```

**Update a user:**
```bash
TOKEN="your-jwt-token-here"
curl -X PUT http://localhost:8080/api/v1/users/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"username":"john_updated","email":"john.updated@example.com"}'
```

**Delete a user (soft delete):**
```bash
TOKEN="your-jwt-token-here"
curl -X DELETE http://localhost:8080/api/v1/users/1 \
  -H "Authorization: Bearer $TOKEN"
```

## Project Structure

```
app/
├── Commands/
│   └── GenerateSwagger.php           # Swagger doc generator
├── Config/
│   ├── Database.php                  # Database configuration
│   ├── Filters.php                   # Filter registration (JwtAuth)
│   ├── OpenApi.php                   # OpenAPI base configuration
│   └── Routes.php                    # API routes
├── Controllers/
│   ├── ApiController.php             # Base API controller
│   └── Api/
│       └── V1/
│           ├── AuthController.php    # Authentication endpoints
│           └── UserController.php    # User CRUD endpoints
├── Entities/
│   └── UserEntity.php                # User data model
├── Filters/
│   └── JwtAuthFilter.php             # JWT authentication filter
├── Models/
│   └── UserModel.php                 # User database model
├── Services/
│   ├── JwtService.php                # JWT token operations
│   └── UserService.php               # User business logic
└── Database/
    ├── Migrations/
    │   ├── *_CreateUsersTable.php
    │   └── *_AddPasswordToUsers.php
    └── Seeds/
        └── UserSeeder.php
public/
└── swagger.json                      # Generated OpenAPI documentation
```

## ApiController Base

This project uses a custom `ApiController` base class (from [ci4-api-base](https://github.com/dcardenasl/ci4-api-base)) that provides:

### Features

- **Automatic Request Data Aggregation**: Unifies GET, POST, JSON, files, and route parameters into a single array
- **Automatic Exception Handling**: Maps exceptions to appropriate HTTP status codes
- **Automatic Response Formatting**: Generates consistent JSON responses
- **Reduced Boilerplate**: Controllers become simple, one-line method definitions

### Automatic Request Data Collection

The `ApiController` automatically collects and merges data from multiple sources:

1. Query parameters (`?key=value`)
2. POST data (form-data)
3. JSON body (`application/json`)
4. File uploads
5. Route parameters (passed manually)

**Example:** For a request like:
```bash
curl -X PUT "http://localhost:8080/api/v1/users/1?debug=true" \
  -H "Content-Type: application/json" \
  -d '{"username":"updated"}'
```

The service receives:
```php
[
    'id' => 1,              // From route parameter
    'debug' => 'true',      // From query string
    'username' => 'updated' // From JSON body
]
```

### Standardized Error Handling

The `ApiController` automatically handles exceptions:

- `InvalidArgumentException` → 400 Bad Request
- `RuntimeException` → 500 Internal Server Error
- Other exceptions → 400 Bad Request (default)

Services can throw exceptions for error cases:
```php
// In service
if (!$user) {
    throw new \InvalidArgumentException('User not found');
}
```

The controller automatically converts this to:
```json
{
    "error": "User not found"
}
```

### Simplified Controller Code

**Before (without ApiController):**
```php
public function index(): ResponseInterface
{
    try {
        $users = $this->userService->getAllUsers();
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $users,
        ]);
    } catch (\Exception $e) {
        return $this->response->setStatusCode(500)->setJSON([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
```

**After (with ApiController):**
```php
public function index(): ResponseInterface
{
    return $this->handleRequest('index');
}
```

**Result:** ~62% less code, no boilerplate!

### Creating New API Controllers

To create a new resource controller:

1. **Create the controller extending `ApiController`:**

```php
<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Services\ProductService;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends ApiController
{
    protected ProductService $productService;

    public function __construct()
    {
        // Initialize your service
        $this->productService = new ProductService();
    }

    protected function getService(): object
    {
        return $this->productService;
    }

    protected function getSuccessStatus(string $method): int
    {
        return match ($method) {
            'store' => 201,    // Created
            default => 200,    // OK
        };
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');
    }

    public function show($id = null): ResponseInterface
    {
        return $this->handleRequest('show', ['id' => $id]);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store');
    }

    public function update($id = null): ResponseInterface
    {
        return $this->handleRequest('update', ['id' => $id]);
    }

    public function delete($id = null): ResponseInterface
    {
        return $this->handleRequest('destroy', ['id' => $id]);
    }
}
```

2. **Create the service with RESTful methods:**

Services must implement these methods:
- `index(array $data): array` - List all resources
- `show(array $data): array` - Get single resource
- `store(array $data): array` - Create resource
- `update(array $data): array` - Update resource
- `destroy(array $data): array` - Delete resource

Each service method should return arrays with this structure:

```php
// Success with data
return ['status' => 'success', 'data' => $items];

// Success with message
return ['status' => 'success', 'message' => 'Resource deleted'];

// Validation errors (triggers 400)
return ['errors' => ['field' => 'Error message']];
```

3. **Add routes:**

```php
// In app/Config/Routes.php
$routes->resource('api/v1/products', [
    'controller' => 'Api\V1\ProductController'
]);
```

## JWT Authentication

### How It Works

1. **Register/Login**: User provides credentials, receives JWT token
2. **Token Storage**: Client stores token (localStorage, cookie, etc.)
3. **API Requests**: Client includes token in Authorization header
4. **Validation**: JwtAuthFilter validates token on protected routes
5. **Access**: Valid token grants access to protected endpoints

### Token Details

- **Algorithm**: HS256 (HMAC with SHA-256)
- **Expiration**: 1 hour (configurable)
- **Payload**: `uid` (user ID), `role`, `iat` (issued at), `exp` (expiration)
- **Security**: Bcrypt password hashing, passwords never exposed in responses

### Protected Routes

All routes under the `jwtauth` filter require authentication:
```php
// In app/Config/Routes.php
$routes->group('', ['filter' => 'jwtauth'], function($routes) {
    $routes->get('auth/me', 'AuthController::me');
    $routes->get('users', 'UserController::index');
    // ... other protected routes
});
```

## Architecture

This project follows a layered architecture pattern with ApiController as the base:

**ApiController Base** (`app/Controllers/ApiController.php`)
   - Automatic request data aggregation
   - Automatic exception handling
   - Automatic response formatting
   - Reduces boilerplate code

1. **Controller Layer** (`app/Controllers/Api/V1/`)
   - Extends ApiController
   - Simple one-line method definitions
   - Delegates to Service layer via `handleRequest()`

2. **Service Layer** (`app/Services/`)
   - Contains business logic
   - RESTful method names (index, show, store, update, destroy)
   - Returns standardized arrays
   - Throws exceptions for error cases

3. **Repository Layer** (`app/Repositories/`)
   - Handles database operations
   - Query builder usage
   - Returns Entity objects

4. **Entity Layer** (`app/Entities/`)
   - Data models
   - Type casting and date handling
   - Provides `toArray()` for JSON serialization

## Commands

### Database Commands

```bash
php spark migrate              # Run all pending migrations
php spark migrate:status       # Check migration status
php spark migrate:rollback     # Rollback last batch
php spark db:table users       # View users table structure
php spark db:seed UserSeeder   # Seed sample data
```

### API Documentation

```bash
php spark swagger:generate     # Generate OpenAPI documentation
```

This command scans your controllers for OpenAPI attributes and generates `public/swagger.json`.

### Development

```bash
php spark serve                # Start development server (localhost:8080)
php spark routes               # List all registered routes
php spark list                 # Show all available commands
```

## Testing

Run tests (when configured):
```bash
vendor/bin/phpunit
```

The project includes GitHub Actions CI that automatically:
- Tests on PHP 8.1, 8.2, and 8.3
- Sets up MySQL service
- Runs migrations
- Executes tests

## Development

### Creating New Migrations

```bash
php spark make:migration CreateTableName
```

### Creating New Seeders

```bash
php spark make:seeder TableNameSeeder
```

### Creating New Resources

Follow the layered architecture:
1. Create Entity in `app/Entities/` (data model with `toArray()`)
2. Create Repository in `app/Repositories/` (database operations)
3. Create Service in `app/Services/` (RESTful methods: index, show, store, update, destroy)
4. Create Controller in `app/Controllers/Api/V1/` (extend `ApiController`, ~30 lines)
5. Add routes in `app/Config/Routes.php` (use `$routes->resource()`)

With ApiController, a new resource can be implemented in ~30 minutes vs 2-3 hours with manual implementation.

## Security Features

- ✅ **JWT Authentication** - Stateless token-based authentication
- ✅ **Password Hashing** - Bcrypt with automatic salt generation
- ✅ **Password Hiding** - Never exposed in API responses
- ✅ **Token Expiration** - 1-hour lifetime (configurable)
- ✅ **Bearer Token** - Standard Authorization header format
- ✅ **Input Validation** - Model-level validation rules
- ✅ **Soft Deletes** - Recoverable user deletion
- ✅ **SQL Injection Protection** - Query builder parameterization
- ✅ **CSRF Protection** - Available for form submissions
- ✅ **XSS Protection** - Output escaping in responses

### Security Best Practices

1. **Change JWT Secret**: Update `JWT_SECRET_KEY` in production to a strong random value
2. **Use HTTPS**: Always use HTTPS in production
3. **Rate Limiting**: Consider adding rate limiting for auth endpoints
4. **Token Refresh**: Implement refresh tokens for long-lived sessions
5. **Environment Variables**: Never commit `.env` file to version control
6. **Database Credentials**: Use strong passwords and restrict access
7. **Error Messages**: Don't expose sensitive information in error responses

## OpenAPI Documentation

### Viewing Documentation

1. **JSON Format**: http://localhost:8080/swagger.json
2. **Swagger UI**: Import the JSON into [Swagger UI](https://editor.swagger.io/)
3. **Postman**: Import as OpenAPI 3.0 spec

### Regenerating Docs

After modifying controllers or adding new endpoints:
```bash
php spark swagger:generate
```

### Adding Documentation to New Endpoints

Use PHP 8 attributes on your controller methods:

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/products',
    summary: 'Get all products',
    security: [['bearerAuth' => []]],
    tags: ['Products'],
)]
#[OA\Response(
    response: 200,
    description: 'List of products',
    content: new OA\JsonContent(/* ... */)
)]
public function index(): ResponseInterface
{
    return $this->handleRequest('index');
}
```

## Testing

This project includes comprehensive PHPUnit tests covering all layers of the application.

### Test Suite Summary

- **49 tests** with **166 assertions** - All Passing ✓
- **AuthControllerTest** (12 tests) - API endpoint testing
- **UserServiceTest** (20 tests) - Business logic testing
- **UserModelTest** (17 tests) - Database layer testing

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run without coverage report
vendor/bin/phpunit --no-coverage

# Run specific test suite
vendor/bin/phpunit tests/Controllers/AuthControllerTest.php

# Run specific test method
vendor/bin/phpunit --filter testLoginSuccess
```

### Test Configuration

Tests use a separate database (`ci4_test`) configured in `phpunit.xml`. The test database is automatically:
- Created before tests run
- Migrated with latest schema
- Seeded with test data
- Reset between test classes

### Writing Tests

The project includes helpful test traits:

- `AuthenticationTrait` - JWT token generation helpers
- `DatabaseTestTrait` - Database assertion helpers
- `FeatureTestTrait` - HTTP request testing
- `DatabaseTestTrait` - Database migrations and seeding

Example test:

```php
public function testLoginSuccess()
{
    $response = $this->withBodyFormat('json')
        ->post('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'testpass123',
        ]);

    $response->assertStatus(200);
    $response->assertJSONFragment(['success' => true]);

    $json = json_decode($response->getJSON());
    $this->assertObjectHasProperty('token', $json->data);
}
```

For detailed testing documentation, see [TESTING.md](TESTING.md).

## CI/CD

This project uses GitHub Actions for continuous integration and deployment.

### Automated Workflows

1. **CI Workflow** (`.github/workflows/ci.yml`)
   - Runs on every push and pull request
   - Tests on PHP 8.2 and 8.3
   - Includes MySQL 8.0 test database
   - Executes full PHPUnit test suite
   - Validates composer.json
   - Caches dependencies for faster builds

2. **Security Scan** (`.github/workflows/security.yml`)
   - Runs on push, pull requests, and weekly schedule
   - Checks for security vulnerabilities with `composer audit`
   - Scans for hardcoded secrets in code
   - Verifies .env files are not committed
   - Validates .gitignore configuration

### CI Pipeline Steps

When you push code or create a pull request, the CI automatically:

1. Checks out your code
2. Sets up PHP 8.2 and 8.3 environments
3. Installs dependencies with composer
4. Creates test environment from .env.example
5. Generates secure JWT and encryption keys
6. Configures MySQL test database
7. Runs all 49 PHPUnit tests
8. Reports results

### Viewing CI Results

Check the status of workflows:
- Go to the **Actions** tab in your GitHub repository
- Click on any workflow run to see detailed logs
- Failed tests will show error messages and stack traces

### Running Tests Locally Before Push

Ensure tests pass before pushing:

```bash
# Run all tests
vendor/bin/phpunit --no-coverage

# Run with detailed output
vendor/bin/phpunit --testdox

# Validate composer
composer validate --strict

# Check for security issues
composer audit
```

### CI Environment Configuration

The CI automatically configures:
- Test database credentials (root/root)
- JWT secret key generation
- Encryption key generation
- Environment set to "testing"

No manual configuration needed!

### Branch Protection (Recommended)

For production use, enable branch protection rules:

1. Go to Settings → Branches → Add rule
2. Branch name pattern: `main`
3. Enable:
   - ✓ Require status checks before merging
   - ✓ Require branches to be up to date
   - ✓ Select CI workflows as required checks
   - ✓ Require pull request reviews

This ensures all code is tested before merging to main.

For detailed CI/CD documentation, see [CI_CD.md](CI_CD.md).

## Dependencies

### Core Dependencies
- `codeigniter4/framework` ^4.5 - CodeIgniter framework
- `firebase/php-jwt` ^7.0 - JWT token handling
- `zircote/swagger-php` ^6.0 - OpenAPI documentation generation

### Development Dependencies
- `phpunit/phpunit` - Unit testing
- `fakerphp/faker` - Test data generation
- `mikey179/vfsstream` - Virtual filesystem for tests

## Troubleshooting

### "Authorization header missing"
**Cause**: Request doesn't include Bearer token
**Solution**: Add header: `Authorization: Bearer {your-token}`

### "Invalid or expired token"
**Cause**: Token expired (>1 hour old) or invalid
**Solution**: Login again to get a fresh token

### "Class not found" errors
**Cause**: Composer autoload not updated
**Solution**: Run `composer dump-autoload`

### Swagger generation fails
**Cause**: PHP version or annotation syntax
**Solution**: Ensure PHP 8.0+ and check OpenAPI attribute syntax

### Database connection failed
**Cause**: Incorrect database credentials
**Solution**: Verify `.env` database settings and ensure MySQL is running

## Configuration Files

### Environment Files

| File | Purpose | Git Tracked |
|------|---------|-------------|
| `.env.example` | Template for local development | ✅ Yes |
| `.env` | Actual local configuration | ❌ No (ignored) |
| `.env.docker.example` | Template for Docker | ✅ Yes |
| `.env.docker` | Actual Docker configuration | ❌ No (ignored) |

**Setup Process:**
```bash
# For local development
cp .env.example .env
# Edit .env with your credentials

# For Docker
cp .env.docker.example .env.docker
# Edit .env.docker with secure passwords
```

### Security

**Before first commit:**
1. Verify `.env` and `.env.docker` are in `.gitignore`
2. Check no credentials in `.env.example` or `.env.docker.example`
3. Generate secure keys for `JWT_SECRET_KEY` and `encryption.key`
4. Read [SECURITY.md](SECURITY.md) for complete security guidelines

**Never commit:**
- `.env` or `.env.docker` (contain real credentials)
- Private keys (`.key`, `.pem` files)
- Database backups (`.sql` files)
- Any file with sensitive information

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. **Important**: Never commit sensitive credentials
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

**Pre-commit checklist:**
- [ ] No credentials in committed files
- [ ] `.env` files are ignored by git
- [ ] Example files use placeholders only
- [ ] All tests pass
- [ ] Code follows project standards

## License

This project is open-sourced software licensed under the MIT license.

## About CodeIgniter

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).
