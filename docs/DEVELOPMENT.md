# Development Guide

Complete architecture, patterns, and best practices for developing with this CodeIgniter 4 API starter kit.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Project Structure](#project-structure)
3. [Layered Architecture](#layered-architecture)
4. [API Controller Base](#apicontroller-base)
5. [Authentication System](#authentication-system)
6. [OpenAPI Documentation](#openapi-documentation)
7. [Creating New Resources](#creating-new-resources)
8. [Database Management](#database-management)
9. [Testing Strategy](#testing-strategy)
10. [Security Considerations](#security-considerations)
11. [Troubleshooting](#troubleshooting)

## Architecture Overview

This project follows a **clean layered architecture** with clear separation of concerns:

```
Request → Controller → Service → Model → Entity → Database
                ↓
            Response
```

### Design Principles

- **Separation of Concerns**: Each layer has a single, well-defined responsibility
- **Dependency Injection**: Services injected through Config\Services
- **Convention over Configuration**: Follow established patterns for consistency
- **DRY (Don't Repeat Yourself)**: Reusable components and base classes
- **RESTful Design**: Standard HTTP methods and status codes

## Project Structure

```
app/
├── Commands/
│   └── GenerateSwagger.php         # Swagger/OpenAPI documentation generator
├── Config/
│   ├── Database.php                # Database configuration
│   ├── Filters.php                 # Filter registration (JwtAuth)
│   ├── OpenApi.php                 # OpenAPI base configuration
│   ├── Routes.php                  # API route definitions
│   └── Services.php                # Service container
├── Controllers/
│   ├── ApiController.php           # Base API controller with auto-handling
│   └── Api/V1/
│       ├── AuthController.php      # Authentication endpoints
│       └── UserController.php      # User CRUD endpoints
├── Documentation/                  # Modular OpenAPI schemas
│   ├── Schemas/
│   │   ├── UserSchema.php          # User data model (used 7x)
│   │   └── AuthTokenSchema.php     # JWT token response
│   ├── Responses/
│   │   ├── UnauthorizedResponse.php          # 401 response
│   │   └── ValidationErrorResponse.php       # 400/422 response
│   └── RequestBodies/
│       ├── LoginRequest.php
│       ├── RegisterRequest.php
│       ├── CreateUserRequest.php
│       └── UpdateUserRequest.php
├── Entities/
│   └── UserEntity.php              # User data model
├── Filters/
│   └── JwtAuthFilter.php           # JWT authentication middleware
├── Models/
│   └── UserModel.php               # User database operations
├── Services/
│   ├── JwtService.php              # JWT token operations
│   └── UserService.php             # User business logic
└── Database/
    ├── Migrations/
    │   ├── 2026-01-28-014712_CreateUsersTable.php
    │   └── 2026-01-28-070454_AddPasswordToUsers.php
    └── Seeds/
        └── UserSeeder.php
```

## Layered Architecture

### 1. Controller Layer

**Responsibility**: Handle HTTP requests and responses

**Pattern**: Extend `ApiController` for automatic request/response handling

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
        return $this->handleRequest('index');
    }
}
```

**Benefits**:
- ~62% less code than manual implementation
- Automatic request data aggregation
- Automatic exception handling
- Consistent response formatting

### 2. Service Layer

**Responsibility**: Business logic and orchestration

**Methods**: RESTful method names matching controller actions

```php
class ProductService
{
    public function index(array $data): array
    {
        // Business logic
        $products = $this->productModel->findAll();
        
        return [
            'status' => 'success',
            'data' => $products
        ];
    }

    public function show(array $data): array
    {
        $id = $data['id'] ?? null;
        
        $product = $this->productModel->find($id);
        
        if (!$product) {
            throw new \InvalidArgumentException('Product not found');
        }
        
        return [
            'status' => 'success',
            'data' => $product
        ];
    }

    public function store(array $data): array
    {
        $product = $this->productModel->insert($data);
        
        return [
            'status' => 'success',
            'data' => $product
        ];
    }

    public function update(array $data): array
    {
        $id = $data['id'] ?? null;
        unset($data['id']);
        
        $this->productModel->update($id, $data);
        
        return [
            'status' => 'success',
            'data' => $this->productModel->find($id)
        ];
    }

    public function destroy(array $data): array
    {
        $id = $data['id'] ?? null;
        
        $this->productModel->delete($id);
        
        return [
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ];
    }
}
```

**Service Response Formats**:

```php
// Success with data
return ['status' => 'success', 'data' => $items];

// Success with message
return ['status' => 'success', 'message' => 'Action completed'];

// Validation errors (auto-converted to 400)
return ['errors' => ['field' => 'Error message']];

// Exceptions (use for 400/404/500)
throw new \InvalidArgumentException('Resource not found');
throw new \RuntimeException('Server error');
```

### 3. Model Layer

**Responsibility**: Database operations and validation

```php
class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $returnType = ProductEntity::class;
    protected $allowedFields = ['name', 'price', 'description'];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    
    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[255]',
        'price' => 'required|decimal',
        'description' => 'permit_empty|max_length[1000]'
    ];
}
```

### 4. Entity Layer

**Responsibility**: Data model representation

```php
class ProductEntity extends Entity
{
    protected $datamap = [];
    protected $casts = [
        'id' => 'integer',
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function toArray(): array
    {
        $data = parent::toArray();
        
        // Format dates
        if (isset($data['created_at'])) {
            $data['created_at'] = $data['created_at']->format('Y-m-d H:i:s');
        }
        
        return $data;
    }
}
```

## ApiController Base

The `ApiController` provides automatic request/response handling.

### Automatic Request Data Aggregation

Combines all request sources into a single array:

```php
// Request: PUT /api/v1/users/1?debug=true
// Body: {"username":"john"}

// Service receives:
[
    'id' => 1,              // Route parameter
    'debug' => 'true',      // Query string
    'username' => 'john'    // JSON body
]
```

**Data Sources (priority order)**:
1. Route parameters (highest)
2. JSON body
3. POST data
4. GET parameters
5. Uploaded files (lowest)

### Automatic Exception Handling

```php
// Service throws exception
throw new \InvalidArgumentException('User not found');

// ApiController catches and returns
{
    "error": "User not found"
}
// HTTP 400 Bad Request
```

**Exception Mapping**:
- `InvalidArgumentException` → 400 Bad Request
- `RuntimeException` → 500 Internal Server Error
- Other exceptions → 400 Bad Request (default)

### Required Methods

```php
abstract protected function getService(): object;
abstract protected function getSuccessStatus(string $method): int;
```

## Authentication System

### JWT Implementation

**Token Generation** (`app/Services/JwtService.php`):

```php
public function generateToken(int $userId, string $role): string
{
    $payload = [
        'uid' => $userId,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + (60 * 60) // 1 hour
    ];

    return JWT::encode($payload, $this->secretKey, 'HS256');
}
```

**Token Validation** (`app/Filters/JwtAuthFilter.php`):

```php
public function before(RequestInterface $request, $arguments = null)
{
    $authHeader = $request->getHeader('Authorization');
    
    if (!$authHeader) {
        return Services::response()
            ->setJSON(['success' => false, 'message' => 'Authorization header missing'])
            ->setStatusCode(401);
    }

    $token = str_replace('Bearer ', '', $authHeader->getValue());
    
    try {
        $decoded = $this->jwtService->validateToken($token);
        $request->userId = $decoded->uid;
        $request->userRole = $decoded->role;
    } catch (\Exception $e) {
        return Services::response()
            ->setJSON(['success' => false, 'message' => 'Invalid or expired token'])
            ->setStatusCode(401);
    }
}
```

### Protected Routes

```php
// app/Config/Routes.php
$routes->group('api/v1', function($routes) {
    // Public routes
    $routes->post('auth/login', 'Api\V1\AuthController::login');
    $routes->post('auth/register', 'Api\V1\AuthController::register');

    // Protected routes
    $routes->group('', ['filter' => 'jwtauth'], function($routes) {
        $routes->get('auth/me', 'Api\V1\AuthController::me');
        $routes->resource('users', ['controller' => 'Api\V1\UserController']);
    });
});
```

## OpenAPI Documentation

### Modular Schema Architecture

Documentation is separated into reusable components in `app/Documentation/`:

**Schemas** (Data Models):
```php
// app/Documentation/Schemas/ProductSchema.php
#[OA\Schema(
    schema: 'Product',
    title: 'Product',
    required: ['id', 'name', 'price'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Widget'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
    ]
)]
class ProductSchema {}
```

**Request Bodies**:
```php
// app/Documentation/RequestBodies/CreateProductRequest.php
#[OA\RequestBody(
    request: 'CreateProductRequest',
    required: true,
    content: new OA\JsonContent(
        required: ['name', 'price'],
        properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'price', type: 'number', format: 'float'),
        ]
    )
)]
class CreateProductRequest {}
```

**Standard Responses**:
```php
// app/Documentation/Responses/NotFoundResponse.php
#[OA\Response(
    response: 'NotFoundResponse',
    description: 'Resource not found',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'error', type: 'string', example: 'Resource not found'),
        ]
    )
)]
class NotFoundResponse {}
```

### Using Documentation in Controllers

```php
#[OA\Get(
    path: '/api/v1/products',
    summary: 'Get all products',
    security: [['bearerAuth' => []]],
    tags: ['Products']
)]
#[OA\Response(
    response: 200,
    description: 'List of products',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'status', type: 'string', example: 'success'),
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(ref: '#/components/schemas/Product')
            )
        ]
    )
)]
#[OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse')]
public function index(): ResponseInterface
{
    return $this->handleRequest('index');
}
```

### Generating Documentation

```bash
php spark swagger:generate
```

**Output**:
```
Statistics:
  Endpoints: 8
  Schemas: 3
  Reusable Responses: 2
  Request Bodies: 4
```

## Creating New Resources

### Step-by-Step Guide

**1. Create Migration**:

```bash
php spark make:migration CreateProductsTable
```

```php
// app/Database/Migrations/YYYY-MM-DD-HHMMSS_CreateProductsTable.php
public function up()
{
    $this->forge->addField([
        'id' => ['type' => 'INT', 'auto_increment' => true],
        'name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'price' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        'created_at' => ['type' => 'DATETIME', 'null' => true],
        'updated_at' => ['type' => 'DATETIME', 'null' => true],
        'deleted_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->createTable('products');
}
```

**2. Create Entity**: `app/Entities/ProductEntity.php`

**3. Create Model**: `app/Models/ProductModel.php`

**4. Create Service**: `app/Services/ProductService.php`

**5. Create Controller**: `app/Controllers/Api/V1/ProductController.php`

**6. Create OpenAPI Schema**: `app/Documentation/Schemas/ProductSchema.php`

**7. Create Request Bodies**:
- `app/Documentation/RequestBodies/CreateProductRequest.php`
- `app/Documentation/RequestBodies/UpdateProductRequest.php`

**8. Add Routes**:

```php
// app/Config/Routes.php
$routes->group('', ['filter' => 'jwtauth'], function($routes) {
    $routes->resource('products', ['controller' => 'Api\V1\ProductController']);
});
```

**9. Run Migration and Generate Docs**:

```bash
php spark migrate
php spark swagger:generate
```

**Estimated Time**: ~30 minutes for complete CRUD resource

## Database Management

### Migrations

```bash
# Create migration
php spark make:migration CreateTableName

# Run migrations
php spark migrate

# Rollback migrations
php spark migrate:rollback

# Check status
php spark migrate:status

# Refresh (rollback all + migrate)
php spark migrate:refresh
```

### Seeders

```bash
# Create seeder
php spark make:seeder ProductSeeder

# Run seeder
php spark db:seed ProductSeeder

# Run all seeders
php spark db:seed
```

### Soft Deletes

Models using soft deletes:

```php
protected $useSoftDeletes = true;
protected $deletedField = 'deleted_at';

// Query without deleted records (default)
$products = $this->productModel->findAll();

// Include deleted records
$allProducts = $this->productModel->withDeleted()->findAll();

// Only deleted records
$deleted = $this->productModel->onlyDeleted()->findAll();

// Permanently delete
$this->productModel->delete($id, true);
```

## Testing Strategy

### Test Structure

```
tests/
├── Controllers/
│   ├── AuthControllerTest.php      # API endpoint tests
│   └── UserControllerTest.php
├── Services/
│   └── UserServiceTest.php         # Business logic tests
├── Models/
│   └── UserModelTest.php           # Database tests
└── Support/
    ├── TestCase.php
    └── Traits/
        └── AuthenticationTrait.php
```

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific test file
vendor/bin/phpunit tests/Controllers/AuthControllerTest.php

# Specific test method
vendor/bin/phpunit --filter testLoginSuccess

# With coverage (requires xdebug)
vendor/bin/phpunit --coverage-html coverage/
```

### Example Test

```php
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\TestCase;

class ProductControllerTest extends TestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('products')->truncate();
    }

    public function testListProducts()
    {
        $token = $this->generateAuthToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/v1/products');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);
    }
}
```

## Security Considerations

### Environment Variables

**Never commit**:
- `.env` (contains secrets)
- `.env.docker` (contains passwords)
- Private keys (`.key`, `.pem`)

**Always commit**:
- `.env.example` (template with placeholders)
- `.env.docker.example` (template)

### JWT Security

```php
// Strong secret key (64+ characters)
JWT_SECRET_KEY = 'your-long-random-secret-key-here'

// Token expiration (1 hour recommended)
$exp = time() + (60 * 60);

// Secure algorithm
JWT::encode($payload, $secretKey, 'HS256');
```

### Password Security

```php
// Hashing (automatic in UserModel)
password_hash($password, PASSWORD_BCRYPT);

// Verification
password_verify($inputPassword, $hashedPassword);

// Never expose in responses (in UserEntity)
protected $hidden = ['password'];
```

### Input Validation

```php
// Model validation rules
protected $validationRules = [
    'email' => 'required|valid_email|is_unique[users.email]',
    'username' => 'required|min_length[3]|max_length[50]|alpha_numeric',
    'password' => 'required|min_length[8]|regex_match[/[A-Z]/]|regex_match[/[0-9]/]'
];
```

### SQL Injection Protection

```php
// ✅ Safe (Query Builder)
$this->db->table('users')->where('id', $id)->get();

// ❌ Unsafe (raw SQL)
$this->db->query("SELECT * FROM users WHERE id = $id");

// ✅ Safe (parameterized raw SQL)
$this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
```

## Troubleshooting

### Common Issues

**"Authorization header missing"**
- Check request includes: `Authorization: Bearer {token}`
- Verify JwtAuthFilter is registered in `app/Config/Filters.php`

**"Invalid or expired token"**
- Token expired (1 hour lifetime) - login again
- JWT_SECRET_KEY mismatch - verify .env configuration
- Token format incorrect - ensure "Bearer " prefix

**"Class not found"**
- Run: `composer dump-autoload`
- Check namespace matches directory structure
- Verify file naming (case-sensitive on Linux)

**Swagger generation fails**
- PHP 8.0+ required for attributes
- Check OpenAPI attribute syntax
- Verify file permissions on `public/`

**Database connection failed**
- Check MySQL is running
- Verify credentials in `.env`
- Test connection: `php spark db:table users`

### Debug Mode

```php
// .env
CI_ENVIRONMENT = development  # Shows detailed errors

// For production
CI_ENVIRONMENT = production   # Hides sensitive errors
```

## Performance Optimization

### Database

```php
// Eager loading relationships
$users = $this->userModel
    ->select('users.*, roles.name as role_name')
    ->join('roles', 'roles.id = users.role_id')
    ->findAll();

// Query caching
$products = cache()->remember('products_all', 300, function() {
    return $this->productModel->findAll();
});
```

### Response Caching

```php
// app/Config/Routes.php
$routes->get('products', 'ProductController::index', ['filter' => 'cache:300']);
```

### Profiling

```bash
# Enable profiler in development
// .env
app.toolbarEnabled = true
```

---

This guide provides the foundation for developing with this API starter. For specific use cases, refer to existing code examples in `app/Controllers/Api/V1/` and `app/Services/`.
