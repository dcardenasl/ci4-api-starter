# CI4 API Starter

A CodeIgniter 4 REST API starter project with layered architecture (Controller → Service → Repository → Entity).

## Features

- Clean layered architecture
- RESTful API with JSON responses
- MySQL database with migrations
- GitHub Actions CI/CD
- CRUD operations for User resource
- Environment-based configuration

## Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer
- PHP Extensions:
  - mysqli
  - mbstring
  - intl
  - json

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
cp env .env
```

Edit `.env` and configure your database settings:
```
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080'

database.default.hostname = 127.0.0.1
database.default.database = ci4_api
database.default.username = root
database.default.password = root
database.default.DBDriver = MySQLi
database.default.port = 3306
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_general_ci
```

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

6. (Optional) Seed the database with sample data:
```bash
php spark db:seed UserSeeder
```

## Usage

Start the development server:
```bash
php spark serve
```

The API will be available at `http://localhost:8080`

## API Endpoints

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/users` | Get all users |
| GET | `/api/v1/users/{id}` | Get user by ID |
| POST | `/api/v1/users` | Create new user |
| PUT | `/api/v1/users/{id}` | Update user |
| DELETE | `/api/v1/users/{id}` | Delete user |

### Example Requests

**Get all users:**
```bash
curl http://localhost:8080/api/v1/users
```

**Get user by ID:**
```bash
curl http://localhost:8080/api/v1/users/1
```

**Create a new user:**
```bash
curl -X POST http://localhost:8080/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{"username":"john_doe","email":"john@example.com"}'
```

**Update a user:**
```bash
curl -X PUT http://localhost:8080/api/v1/users/1 \
  -H "Content-Type: application/json" \
  -d '{"username":"john_updated","email":"john.updated@example.com"}'
```

**Delete a user:**
```bash
curl -X DELETE http://localhost:8080/api/v1/users/1
```

## Project Structure

```
app/
├── Controllers/
│   └── Api/
│       └── V1/
│           └── UserController.php    # API endpoints
├── Services/
│   └── UserService.php               # Business logic
├── Repositories/
│   └── UserRepository.php            # Data access layer
├── Entities/
│   └── UserEntity.php                # Data model
├── Database/
│   ├── Migrations/
│   │   └── *_CreateUsersTable.php
│   └── Seeds/
│       └── UserSeeder.php
└── Config/
    ├── Database.php                  # Database configuration
    └── Routes.php                    # API routes
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

## Database Commands

Check migration status:
```bash
php spark migrate:status
```

Run migrations:
```bash
php spark migrate
```

Rollback migrations:
```bash
php spark migrate:rollback
```

View table structure:
```bash
php spark db:table users
```

Seed database:
```bash
php spark db:seed UserSeeder
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

## License

This project is open-sourced software licensed under the MIT license.

## About CodeIgniter

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).
