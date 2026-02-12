# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Server
```bash
php spark serve                  # Start dev server at http://localhost:8080
```

### Testing
```bash
# Run all tests (117 tests)
vendor/bin/phpunit
vendor/bin/phpunit --testdox    # Human-readable test output

# Run specific test suites
vendor/bin/phpunit tests/Unit              # Unit tests (88 tests, fast, no DB)
vendor/bin/phpunit tests/Integration       # Integration tests (19 tests, with DB)
vendor/bin/phpunit tests/Feature           # Feature/Controller tests (10 tests, HTTP)

# Run single test method
vendor/bin/phpunit --filter TestClassName::testMethodName

# Composer aliases
composer test                   # Run all tests
composer cs-check               # Check code style
composer cs-fix                 # Fix code style
```

Test database configuration is in `phpunit.xml` (uses `ci4_test` database).

### Database
```bash
php spark migrate               # Run migrations
php spark migrate:rollback      # Rollback last migration
php spark migrate:refresh       # Rollback all + re-run migrations
php spark db:seed UserSeeder    # Run specific seeder
php spark make:migration CreateTableName  # Create new migration
```

### OpenAPI Documentation
```bash
php spark swagger:generate      # Generate swagger.json from annotations
# View at: http://localhost:8080/swagger.json
```

### Other Utilities
```bash
php spark routes                # List all registered routes
php spark key:generate          # Generate encryption key
composer audit                  # Security vulnerability check
```

## Architecture Overview

This is a **layered REST API** following the pattern: **Controller → Service → Model → Entity**.

### Request/Response Flow

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Filters (CORS, Throttle, JWT Auth, Role Auth)   │
└─────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Controller (extends ApiController)              │
│ - Collects request data via handleRequest()    │
│ - Delegates to service                         │
│ - Returns HTTP response                        │
└─────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Service (implements ServiceInterface)           │
│ - Business logic & validation                  │
│ - Throws exceptions for errors                 │
│ - Returns ApiResponse arrays                   │
└─────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Model (uses Filterable, Searchable traits)      │
│ - Database operations via query builder        │
│ - Data validation rules                        │
│ - Returns Entity objects                       │
└─────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Entity                                          │
│ - Data representation                          │
│ - Computed properties                          │
│ - Field casting                                │
└─────────────────────────────────────────────────┘
```

### Key Components

#### ApiController (`app/Controllers/ApiController.php`)
Base controller that all API controllers extend:
- `handleRequest($method, $item)` - Collects data from GET, POST, JSON, files
- `handleException($e)` - Central exception handling
- Input sanitization (XSS prevention)

**Child controllers must implement:**
```php
protected function getService(): object;
protected function getSuccessStatus(string $method): int;
```

#### ApiResponse (`app/Libraries/ApiResponse.php`)
Standardized response format:
```php
ApiResponse::success($data, $message, $meta)     // 200
ApiResponse::created($data)                       // 201
ApiResponse::deleted($message)                    // 200
ApiResponse::paginated($items, $total, $page, $perPage)
ApiResponse::error($errors, $message, $code)
ApiResponse::validationError($errors)             // 422
ApiResponse::notFound($message)                   // 404
ApiResponse::unauthorized($message)               // 401
ApiResponse::forbidden($message)                  // 403
```

#### Services
All services implement interfaces for testability:
- `AuthServiceInterface` - Login, register, token generation
- `UserServiceInterface` - User CRUD operations
- `JwtServiceInterface` - JWT encode/decode
- `RefreshTokenServiceInterface` - Refresh token lifecycle
- `FileServiceInterface` - File upload/download
- `AuditServiceInterface` - Audit logging
- `VerificationServiceInterface` - Email verification
- `PasswordResetServiceInterface` - Password reset flow

#### Custom Exceptions (`app/Exceptions/`)
```php
throw new NotFoundException('User not found');           // 404
throw new AuthenticationException('Invalid credentials'); // 401
throw new AuthorizationException('Access denied');       // 403
throw new ValidationException('Invalid data', $errors);  // 422
throw new BadRequestException('Invalid request');        // 400
throw new ConflictException('Already exists');           // 409
```

### Route Structure

| Route Pattern | Filter | Description |
|---------------|--------|-------------|
| `/api/v1/auth/login` | throttle | Public auth |
| `/api/v1/auth/register` | throttle | Public registration |
| `/api/v1/users` | jwtauth | Protected read |
| `/api/v1/users` (POST/PUT/DELETE) | jwtauth, roleauth:admin | Admin only |
| `/health`, `/ping` | none | Health checks |

## Adding New Resources

### 1. Create Migration
```bash
php spark make:migration CreateProductsTable
```

### 2. Create Entity
```php
// app/Entities/ProductEntity.php
class ProductEntity extends Entity {
    protected $casts = ['id' => 'integer', 'price' => 'float'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}
```

### 3. Create Model
```php
// app/Models/ProductModel.php
class ProductModel extends Model {
    use Filterable, Searchable;

    protected $table = 'products';
    protected $returnType = ProductEntity::class;
    protected $allowedFields = ['name', 'price', 'description'];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;

    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'created_at'];
}
```

### 4. Create Service Interface & Service
```php
// app/Interfaces/ProductServiceInterface.php
interface ProductServiceInterface {
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}

// app/Services/ProductService.php
class ProductService implements ProductServiceInterface {
    public function __construct(protected ProductModel $productModel) {}

    public function show(array $data): array {
        if (!isset($data['id'])) {
            throw new BadRequestException('ID required');
        }
        $product = $this->productModel->find($data['id']);
        if (!$product) {
            throw new NotFoundException('Product not found');
        }
        return ApiResponse::success($product->toArray());
    }
}
```

### 5. Create Controller
```php
// app/Controllers/Api/V1/ProductController.php
class ProductController extends ApiController {
    protected string $serviceName = 'productService';

    public function index(): ResponseInterface {
        return $this->handleRequest('index');
    }

    public function show($id): ResponseInterface {
        return $this->handleRequest('show', ['id' => $id]);
    }
}
```

### 6. Add Routes
```php
// app/Config/Routes.php
$routes->group('api/v1', ['filter' => 'jwtauth'], function($routes) {
    $routes->get('products', 'Api\V1\ProductController::index');
    $routes->get('products/(:num)', 'Api\V1\ProductController::show/$1');
});
```

## Testing

### Test Structure
```
tests/
├── Unit/                    # 88 tests - No DB, mocked dependencies
│   ├── Libraries/           # ApiResponse tests
│   └── Services/            # Service unit tests
├── Integration/             # 19 tests - Real DB operations
│   ├── Models/              # Model tests with DB
│   └── Services/            # Service integration tests
└── Feature/                 # 10 tests - HTTP endpoint tests
    └── Controllers/         # Full request/response cycle
```

### Writing Unit Tests
```php
class AuthServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // Create mock with anonymous class for query builder methods
        $this->mockUserModel = new class($user) extends UserModel {
            public function where($key, $value = null, ?bool $escape = null): static {
                return $this;
            }
            public function first() {
                return $this->returnUser;
            }
        };
    }

    public function testLoginWithValidCredentialsReturnsUserData(): void
    {
        $result = $this->service->login(['username' => 'test', 'password' => 'pass']);
        $this->assertSuccessResponse($result);
    }
}
```

### Writing Integration Tests
```php
class UserModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // Important: Use app migrations

    public function testInsertCreatesUser(): void
    {
        $userId = $this->userModel->insert([...]);
        $this->assertIsInt($userId);
    }
}
```

### Custom Assertions (`tests/_support/Traits/CustomAssertionsTrait.php`)
```php
$this->assertSuccessResponse($result, 'dataKey');
$this->assertErrorResponse($result, 'errorField');
$this->assertPaginatedResponse($result);
$this->assertValidationErrorResponse($result, ['email', 'password']);
```

## Error Handling

### When to Throw Exceptions
```php
// Resource not found
throw new NotFoundException('User not found');

// Authentication failed
throw new AuthenticationException('Invalid credentials');

// Authorization denied
throw new AuthorizationException('Admin access required');

// Conflict state
throw new ConflictException('Email already verified');

// Bad request
throw new BadRequestException('Invalid file type');
```

### When to Return ApiResponse
```php
// Model validation failed
if (!$this->model->validate($data)) {
    throw new ValidationException('Validation failed', $this->model->errors());
}
```

### Exception to HTTP Status Mapping
| Exception | HTTP Status |
|-----------|-------------|
| NotFoundException | 404 |
| AuthenticationException | 401 |
| AuthorizationException | 403 |
| ValidationException | 422 |
| BadRequestException | 400 |
| ConflictException | 409 |
| Other exceptions | 500 |

## Configuration

### Required Environment Variables
```env
# Security (REQUIRED)
JWT_SECRET_KEY=your-64-char-secret
encryption.key=hex2bin:your-key

# Database
database.default.hostname=localhost
database.default.database=ci4_api
database.default.username=root
database.default.password=

# JWT
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800
```

### Optional Configuration
```env
# Email
EMAIL_FROM_ADDRESS=noreply@example.com
EMAIL_SMTP_HOST=smtp.example.com

# File Storage
STORAGE_DRIVER=local
FILE_MAX_SIZE=10485760
FILE_ALLOWED_TYPES=jpg,jpeg,png,gif,pdf

# Rate Limiting
THROTTLE_LIMIT=60
THROTTLE_WINDOW=60
```

## Common Pitfalls

1. **Always extend ApiController** - Never use base Controller
2. **Services return arrays** - Use `ApiResponse::*()` methods
3. **No business logic in models** - Models are for DB only
4. **Use query builder** - Never raw SQL
5. **Integration tests need `$namespace = 'App'`** - For migrations
6. **Mock CodeIgniter models with anonymous classes** - PHPUnit mocks don't work for query builder methods
7. **Method signature for errors()** - Must be `errors(bool $forceDB = false): array`

## CI/CD

GitHub Actions runs on push to `main`:
- Tests on PHP 8.2, 8.3
- MySQL 8.0 service container
- Code style check (optional, continues on error)

Configuration: `.github/workflows/ci.yml`
