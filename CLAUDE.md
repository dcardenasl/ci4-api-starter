# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Server
```bash
php spark serve                  # Start dev server at http://localhost:8080
```

### Testing
```bash
# Run all tests
vendor/bin/phpunit
vendor/bin/phpunit --testdox    # Human-readable test output

# Run specific test suites
vendor/bin/phpunit tests/Unit              # Unit tests (fast, no DB)
vendor/bin/phpunit tests/Integration       # Integration tests (with DB)
vendor/bin/phpunit tests/Feature           # Feature/Controller tests (HTTP)

# Run single test method
vendor/bin/phpunit --filter TestClassName::testMethodName

# Current test count (dynamic)
vendor/bin/phpunit --list-tests | rg -c "::"

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
php spark db:seed UsersLoadTestSeeder  # Seed load-test users
php spark users:bootstrap-superadmin --email superadmin@example.com --password 'StrongPass123!'  # Bootstrap first superadmin
php spark make:migration CreateTableName  # Create new migration
```

### OpenAPI Documentation
```bash
php spark swagger:generate      # Generate public/swagger.json from app/Documentation/
# View at: http://localhost:8080/swagger.json (served from public/swagger.json)
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

**Child controllers typically only define:**
```php
protected string $serviceName = 'resourceService';
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
| `/api/v1/auth/login` | authThrottle | Public auth |
| `/api/v1/auth/register` | authThrottle | Public registration |
| `/api/v1/auth/refresh` | authThrottle | Public token refresh |
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
├── Unit/                    # No DB, mocked dependencies
│   ├── Libraries/           # ApiResponse tests
│   └── Services/            # Service unit tests
├── Integration/             # Real DB operations
│   ├── Models/              # Model tests with DB
│   └── Services/            # Service integration tests
└── Feature/                 # HTTP endpoint tests
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

## Architectural Exceptions

**CRITICAL:** Not all controllers follow the standard ApiController pattern. These are intentional design decisions:

### Infrastructure Controllers (DO NOT extend ApiController)

**HealthController** and **MetricsController** are infrastructure endpoints, not business logic:

```php
// ✅ CORRECT - Health checks are simple and fast
class HealthController extends Controller {
    public function ping(): ResponseInterface {
        return $this->response->setJSON(['status' => 'ok']);
    }
}
```

**Why they don't extend ApiController:**
1. **Performance**: Called every 5-10 seconds by orchestrators (Kubernetes, Docker Swarm)
2. **No authentication**: Must be publicly accessible
3. **No business logic**: Just report system status
4. **No data processing**: No user input to sanitize
5. **Standard compliance**: Follow industry patterns (Spring Boot HealthIndicator, Express health checks)

**Endpoints that are infrastructure, not API resources:**
- `/health` - Full health check with all components
- `/ping` - Lightweight availability check
- `/ready` - Kubernetes readiness probe
- `/live` - Kubernetes liveness probe
- `/metrics/*` - Monitoring and observability (admin-only, but not CRUD)

### OpenAPI Documentation Pattern

**DO NOT add @OpenApi annotations to controllers.** This project uses **separated documentation files**:

```
app/Documentation/
├── Auth/
│   ├── AuthEndpoints.php
│   ├── LoginRequest.php
│   └── RegisterRequest.php
├── Users/
│   ├── UserEndpoints.php
│   └── UserSchema.php
└── Common/
    ├── UnauthorizedResponse.php
    └── ValidationErrorResponse.php
```

**Generate documentation:**
```bash
php spark swagger:generate  # Generates from app/Documentation/
```

**Why separated files are superior:**
- ✅ Separation of concerns (code ≠ documentation)
- ✅ Reusable schemas across endpoints
- ✅ Easier to maintain and review
- ✅ Cleaner controller code
- ✅ Better for team collaboration

### Explicit Parameters vs Auto-Collection

**Both patterns are valid** depending on context:

```php
// ✅ Explicit parameters (recommended for clarity)
public function sendResetLink(): ResponseInterface {
    $email = $this->request->getVar('email') ?? '';
    return $this->handleRequest('sendResetLink', ['email' => $email]);
}

// ✅ Auto-collection (recommended for standard CRUD)
public function update($id): ResponseInterface {
    return $this->handleRequest('update', ['id' => $id]);
}
```

**Use explicit parameters when:**
- Method signature is not obvious
- Improves code readability
- Makes testing easier
- Parameters come from different sources (headers, query, body)

**Use auto-collection when:**
- Standard CRUD operations (index, show, store, update, destroy)
- All parameters come from request body
- Method signature is self-documenting

### CustomAssertionsTrait Usage

**DO NOT use CustomAssertionsTrait in all tests.** Only for tests that verify ApiResponse structure:

```php
// ✅ CORRECT - Service tests that return ApiResponse
class AuthServiceTest extends CIUnitTestCase {
    use CustomAssertionsTrait;

    public function testLogin() {
        $result = $this->service->login([...]);
        $this->assertSuccessResponse($result);  // ✅
    }
}

// ✅ CORRECT - Testing ApiResponse library itself
class ApiResponseTest extends CIUnitTestCase {
    // ❌ NO CustomAssertionsTrait - would be circular

    public function testSuccess() {
        $result = ApiResponse::success(['id' => 1]);
        $this->assertEquals('success', $result['status']);  // ✅
    }
}

// ✅ CORRECT - Testing validation rules
class CustomRulesTest extends CIUnitTestCase {
    // ❌ NO CustomAssertionsTrait - not testing API responses

    public function testStrongPassword() {
        $result = $this->validation->check($password, 'strong_password');
        $this->assertTrue($result);  // ✅
    }
}
```

**Use CustomAssertionsTrait only for:**
- Service unit tests (AuthService, UserService, FileService, etc.)
- Service integration tests
- Feature/Controller tests

**DO NOT use for:**
- ApiResponse library tests (circular dependency)
- Validation rule tests
- Helper function tests
- Model tests (unless testing service methods)

## Common Pitfalls

1. **Business API controllers extend ApiController** - Infrastructure endpoints (health, metrics) don't
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
