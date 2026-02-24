# Agent Quick Reference - CI4 API Starter

**Purpose**: Essential patterns and conventions for AI agents implementing CRUD resources.
**For detailed architecture**: See `ARCHITECTURE.md` (human reference).

---

## 1. Request Flow (Simplified)

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────┐
│ FILTERS (Middleware)                        │
│ CorsFilter → ThrottleFilter → JwtAuthFilter │
│           → RoleAuthFilter                  │
└─────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ CONTROLLER (app/Controllers/Api/V1/)        │
│ - handleRequest($method, $params)           │
│ - collectRequestData() → sanitize input     │
│ - Delegate to service                       │
│ - Return HTTP response                      │
└─────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ SERVICE (app/Services/)                     │
│ - Validate business rules                   │
│ - Orchestrate operations                    │
│ - Throw custom exceptions                   │
│ - Return ApiResponse arrays                 │
└─────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ MODEL (app/Models/)                         │
│ - Database operations (query builder)       │
│ - Data validation rules                     │
│ - Return Entity objects                     │
└─────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ ENTITY (app/Entities/)                      │
│ - Type casting                              │
│ - Computed properties                       │
│ - Hide sensitive fields (toArray())         │
└─────────────────────────────────────────────┘
```

---

## 2. CRUD Implementation Checklist

When creating a new resource (e.g., "Product"), follow this exact order:

### Step 0: Scaffold First (Required)
```bash
php spark make:crud Product --domain Catalog --route products
```
- Use this command as the default entrypoint for new CRUD resources.
- Only skip it if the user explicitly requests manual file creation.

### Step 1: Migration
```bash
php spark make:migration CreateProductsTable
```
- Always include: `id`, `created_at`, `updated_at`, `deleted_at`
- Add indexes for foreign keys and frequently filtered fields
- Use soft deletes (`deleted_at`)

### Step 2: Entity
```php
// app/Entities/ProductEntity.php
class ProductEntity extends Entity
{
    protected $casts = [
        'id'    => 'integer',
        'price' => 'float',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected array $hidden = ['internal_notes'];  // Sensitive fields
}
```

### Step 3: Model
```php
// app/Models/ProductModel.php
class ProductModel extends Model
{
    use Filterable, Searchable;

    protected $table            = 'products';
    protected $returnType       = ProductEntity::class;
    protected $allowedFields    = ['name', 'price', 'description'];
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'created_at'];
    protected array $sortableFields   = ['id', 'name', 'price', 'created_at'];

    protected $validationRules = [
        'name'  => 'required|max_length[255]',
        'price' => 'required|numeric|greater_than[0]',
    ];
}
```

### Step 4: Service Interface
```php
// app/Interfaces/ProductServiceInterface.php
interface ProductServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}
```

### Step 5: Service Implementation
```php
// app/Services/ProductService.php
class ProductService implements ProductServiceInterface
{
    public function __construct(protected ProductModel $productModel) {}

    public function store(array $data): array
    {
        // Model validation
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->productModel->errors()
            );
        }

        // Business rules validation (if needed)

        // Persist
        $id = $this->productModel->insert($data);
        $product = $this->productModel->find($id);

        return ApiResponse::created($product->toArray());
    }

    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException(
                lang('Api.invalidRequest'),
                ['id' => lang('InputValidation.common.idRequired', ['Id'])]
            );
        }

        $product = $this->productModel->find($data['id']);

        if (!$product) {
            throw new NotFoundException(lang('Products.notFound'));
        }

        return ApiResponse::success($product->toArray());
    }
}
```

### Step 6: Register Service
```php
// app/Config/Services.php
public static function productService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('productService');
    }
    return new \App\Services\ProductService(
        new \App\Models\ProductModel()
    );
}
```

### Step 7: Controller
```php
// app/Controllers/Api/V1/Catalog/ProductController.php
class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    // Methods inherited from ApiController:
    // - index()   -> handleRequest('index')
    // - show($id) -> handleRequest('show', ['id' => $id])
    // - create()  -> handleRequest('store')
    // - update()  -> handleRequest('update', ['id' => $id])
    // - delete()  -> handleRequest('destroy', ['id' => $id])
}
```

### Step 8: Routes
```php
// app/Config/Routes.php
$routes->group('api/v1', ['filter' => 'jwtauth'], function ($routes) {
    // Public read
    $routes->get('products', 'App\Controllers\Api\V1\Catalog\ProductController::index');
    $routes->get('products/(:num)', 'App\Controllers\Api\V1\Catalog\ProductController::show/$1');

    // Admin only
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('products', 'App\Controllers\Api\V1\Catalog\ProductController::create');
        $routes->put('products/(:num)', 'App\Controllers\Api\V1\Catalog\ProductController::update/$1');
        $routes->delete('products/(:num)', 'App\Controllers\Api\V1\Catalog\ProductController::delete/$1');
    });
});
```

### Step 9: Language Files
```php
// app/Language/en/Products.php
return [
    'notFound'       => 'Product not found',
    'createdSuccess' => 'Product created successfully',
    'updatedSuccess' => 'Product updated successfully',
    'deletedSuccess' => 'Product deleted successfully',
];

// app/Language/es/Products.php
return [
    'notFound'       => 'Producto no encontrado',
    'createdSuccess' => 'Producto creado exitosamente',
    'updatedSuccess' => 'Producto actualizado exitosamente',
    'deletedSuccess' => 'Producto eliminado exitosamente',
];
```

Add model/input validation messages in `InputValidation` (not in resource file):

```php
// app/Language/en/InputValidation.php
'product' => [
    'nameRequired' => 'Product name is required',
];

// app/Language/es/InputValidation.php
'product' => [
    'nameRequired' => 'El nombre del producto es obligatorio',
];
```

### Step 10: Tests
Create three test levels:

**Unit Test** (`tests/Unit/Services/ProductServiceTest.php`):
```php
class ProductServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock model with anonymous class for query builder support
        $this->mockModel = new class extends ProductModel {
            public function where($key, $value = null, ?bool $escape = null): static {
                return $this;
            }
        };
        $this->service = new ProductService($this->mockModel);
    }
}
```

### Step 11: i18n Enforcement
```bash
composer i18n-check
```
This is required before opening a PR.

**Integration Test** (`tests/Integration/Models/ProductModelTest.php`):
```php
class ProductModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // CRITICAL: Use app migrations
}
```

**Feature Test** (`tests/Feature/Controllers/ProductControllerTest.php`):
```php
class ProductControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $migrate     = true;
    protected $namespace   = 'App';
}
```

---

## 3. Exception Reference

| Exception | HTTP Status | When to Use |
|-----------|-------------|-------------|
| `NotFoundException` | 404 | Resource not found in database |
| `AuthenticationException` | 401 | Invalid credentials or token |
| `AuthorizationException` | 403 | User lacks required permissions |
| `ValidationException` | 422 | Data validation failed (pass errors array) |
| `BadRequestException` | 400 | Malformed request, missing required params |
| `ConflictException` | 409 | Duplicate entry, state conflict |

**Usage:**
```php
throw new NotFoundException(lang('Products.notFound'));
throw new ValidationException(lang('Api.validationFailed'), $errors);
throw new BadRequestException('Missing required field: price');
```

---

## 4. ApiResponse Reference

All services MUST return arrays using these static methods:

```php
// Success responses
ApiResponse::success($data, $message = null, $meta = [])
ApiResponse::created($data, $message = null)
ApiResponse::deleted($message = null)

// Paginated response
ApiResponse::paginated($items, $total, $page, $perPage)

// Error responses (rarely used - prefer throwing exceptions)
ApiResponse::error($errors, $message = null, $code = null)
ApiResponse::validationError($errors, $message = null)
ApiResponse::notFound($message = null)
ApiResponse::unauthorized($message = null)
ApiResponse::forbidden($message = null)
```

**Example:**
```php
return ApiResponse::success($product->toArray(), lang('Products.createdSuccess'));
return ApiResponse::paginated($items, $total, $page, $perPage);
```

---

## 5. Validation Patterns

### Model Validation Rules
```php
protected $validationRules = [
    'email' => [
        'rules'  => 'required|valid_email|is_unique[users.email,id,{id}]',
        'errors' => [
            'required'    => '{field} is required',
            'valid_email' => 'Please provide a valid email',
            'is_unique'   => 'This email is already registered',
        ],
    ],
    'password' => [
        'rules'  => 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
    ],
];
```

### Common Validation Rules
- `required` - Field is mandatory
- `permit_empty` - Allow empty/null values
- `max_length[N]` - Maximum string length
- `min_length[N]` - Minimum string length
- `is_unique[table.field,id,{id}]` - Unique in database (exclude current ID on update)
- `valid_email` - Email format
- `numeric` - Numeric value
- `greater_than[N]` / `less_than[N]` - Numeric comparisons
- `regex_match[pattern]` - Custom regex validation

---

## 6. Query Features

Models with `Filterable` and `Searchable` traits support:

### Filtering
```bash
GET /api/v1/products?filter[price][gte]=100&filter[name][like]=%phone%
```

**Supported operators:**
- `eq` - Equals (default)
- `neq` - Not equals
- `gt`, `gte` - Greater than (or equal)
- `lt`, `lte` - Less than (or equal)
- `like` - SQL LIKE pattern
- `in` - IN clause (array)

### Searching
```bash
GET /api/v1/products?search=laptop
```
Searches across all fields in `$searchableFields` using FULLTEXT or LIKE.

### Sorting
```bash
GET /api/v1/products?sort=-created_at,name
```
Prefix `-` for descending order. Only fields in `$sortableFields` are allowed.

### Pagination
```bash
GET /api/v1/products?page=2&limit=50
```
Max limit: `PAGINATION_MAX_LIMIT` (env, default: 100)

---

## 7. Common Patterns

### Service with Pagination
```php
public function index(array $data): array
{
    $builder = new QueryBuilder($this->productModel);

    if (!empty($data['filter'])) {
        $builder->filter($data['filter']);
    }

    if (!empty($data['search'])) {
        $builder->search($data['search']);
    }

    if (!empty($data['sort'])) {
        $builder->sort($data['sort']);
    }

    $page = max((int) ($data['page'] ?? 1), 1);
    $limit = (int) ($data['limit'] ?? 20);
    $result = $builder->paginate($page, $limit);

    $result['data'] = array_map(fn($item) => $item->toArray(), $result['data']);

    return ApiResponse::paginated(
        $result['data'],
        $result['total'],
        $result['page'],
        $result['perPage']
    );
}
```

### Service with Ownership Check
```php
public function update(array $data): array
{
    $product = $this->productModel->find($data['id']);

    if (!$product) {
        throw new NotFoundException(lang('Products.notFound'));
    }

    // Check ownership (if applicable)
    if ($product->user_id !== $data['user_id']) {
        throw new AuthorizationException(lang('Api.accessDenied'));
    }

    // Update logic...
}
```

### Service with Relationships
```php
public function store(array $data): array
{
    // Start transaction
    $this->productModel->db->transStart();

    // Create product
    $productId = $this->productModel->insert($data);

    // Create related records
    if (!empty($data['tags'])) {
        foreach ($data['tags'] as $tagId) {
            $this->productTagModel->insert([
                'product_id' => $productId,
                'tag_id'     => $tagId,
            ]);
        }
    }

    $this->productModel->db->transComplete();

    if ($this->productModel->db->transStatus() === false) {
        throw new \Exception('Transaction failed');
    }

    return ApiResponse::created($this->productModel->find($productId)->toArray());
}
```

---

## 8. Security Checklist

- ✅ **Always extend `ApiController`** - Never use base Controller
- ✅ **Input sanitization** - Handled automatically by `collectRequestData()`
- ✅ **SQL injection protection** - Always use query builder, never raw SQL
- ✅ **Mass assignment protection** - Define `$allowedFields` in models
- ✅ **Sensitive fields** - Hide via `$hidden` array in entities
- ✅ **Authentication** - Use `jwtauth` filter on protected routes
- ✅ **Authorization** - Use `roleauth:admin` for admin-only operations
- ✅ **Password hashing** - Use `password_hash($value, PASSWORD_BCRYPT)`
- ✅ **Soft deletes** - Enable `$useSoftDeletes = true` in models
- ✅ **Rate limiting** - Apply `throttle` filter on public endpoints

---

## 9. Testing Checklist

### Unit Tests
- ✅ Mock dependencies with **anonymous classes** (not PHPUnit mocks)
- ✅ Use `CustomAssertionsTrait` for common assertions
- ✅ Test business logic without database
- ✅ Test error handling and exceptions

### Integration Tests
- ✅ Set `protected $namespace = 'App'` to use app migrations
- ✅ Use `DatabaseTestTrait` with `$migrate = true`
- ✅ Test model validation rules
- ✅ Test database operations (insert, update, delete)

### Feature Tests
- ✅ Test full HTTP request/response cycle
- ✅ Test authentication and authorization
- ✅ Test response status codes and structure
- ✅ Test validation error responses

**Run tests:**
```bash
vendor/bin/phpunit                    # All tests
vendor/bin/phpunit tests/Unit         # Fast, no DB
vendor/bin/phpunit tests/Integration  # With DB
vendor/bin/phpunit tests/Feature      # HTTP tests
```

---

## 10. File Naming Conventions

| Type | Location | Naming Pattern | Example |
|------|----------|----------------|---------|
| Migration | `app/Database/Migrations/` | `YYYY-MM-DD-HHMMSS_CreateTableName.php` | `2026-02-11-143000_CreateProductsTable.php` |
| Entity | `app/Entities/` | `{Name}Entity.php` | `ProductEntity.php` |
| Model | `app/Models/` | `{Name}Model.php` | `ProductModel.php` |
| Service Interface | `app/Interfaces/` | `{Name}ServiceInterface.php` | `ProductServiceInterface.php` |
| Service | `app/Services/` | `{Name}Service.php` | `ProductService.php` |
| Controller | `app/Controllers/Api/V1/{Domain}/` | `{Name}Controller.php` | `Catalog/ProductController.php` |
| Unit Test | `tests/Unit/Services/` | `{Name}ServiceTest.php` | `ProductServiceTest.php` |
| Integration Test | `tests/Integration/Models/` | `{Name}ModelTest.php` | `ProductModelTest.php` |
| Feature Test | `tests/Feature/Controllers/{Domain}/` | `{Name}ControllerTest.php` | `Catalog/ProductControllerTest.php` |
| Language File | `app/Language/{lang}/` | `{Name}.php` | `Products.php` |

---

## 11. Common Pitfalls to Avoid

❌ **DON'T** put business logic in controllers
✅ **DO** delegate to services

❌ **DON'T** return entities or models from services
✅ **DO** return `ApiResponse::*()` arrays

❌ **DON'T** use raw SQL queries
✅ **DO** use query builder methods

❌ **DON'T** use PHPUnit mocks for CodeIgniter models
✅ **DO** use anonymous classes with query builder methods

❌ **DON'T** skip `$namespace = 'App'` in integration tests
✅ **DO** set it to load app migrations

❌ **DON'T** expose sensitive fields in API responses
✅ **DO** hide them via `$hidden` in entities

❌ **DON'T** validate only at the model level
✅ **DO** validate both at service level (business rules) and model level (data integrity)

---

## Quick Start Commands

```bash
# Create migration
php spark make:migration CreateProductsTable

# Run migrations
php spark migrate

# Generate OpenAPI docs
php spark swagger:generate

# Run all tests
vendor/bin/phpunit

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# List all routes
php spark routes
```

---

**END OF QUICK REFERENCE**

For comprehensive architectural details, flow diagrams, and advanced patterns, see `docs/ARCHITECTURE.md`.
