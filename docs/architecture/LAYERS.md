# The Four Layers

This document explains each layer in detail: Controller, Service, Model, and Entity.

---

## Controller Layer

**Location:** `app/Controllers/Api/V1/`

**Responsibility:** Handle HTTP requests and responses ONLY.

### ApiController (Base Class)

All API controllers extend `ApiController.php`:

```php
abstract class ApiController extends Controller
{
    use ResponseTrait;

    protected string $serviceName = '';  // Child defines this

    protected function handleRequest(string $method, ?array $params = null): ResponseInterface
    {
        try {
            $data = $this->collectRequestData($params);
            $result = $this->getService()->$method($data);
            $status = $this->getSuccessStatus($method);
            return $this->respond($result, $status);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
```

**Key methods:**
- `handleRequest()` - Template method for all CRUD operations
- `collectRequestData()` - Merge GET, POST, JSON, files, route params
- `sanitizeInput()` - XSS prevention via `strip_tags()`
- `handleException()` - Convert exceptions to HTTP responses

### Child Controllers

```php
class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    // That's it! Inherited methods:
    // - index()   → handleRequest('index')
    // - show($id) → handleRequest('show', ['id' => $id])
    // - create()  → handleRequest('store')
    // - update($id) → handleRequest('update', ['id' => $id])
    // - delete($id) → handleRequest('destroy', ['id' => $id])
}
```

### Rules
- ❌ NO business logic
- ❌ NO database queries
- ❌ NO validation logic
- ✅ ONLY HTTP handling
- ✅ Delegate to service
- ✅ Return HTTP response

---

## Service Layer

**Location:** `app/Services/`

**Responsibility:** Business logic, validation, orchestration.

### Pattern

```php
// Interface first (app/Interfaces/)
interface ProductServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}

// Implementation (app/Services/)
class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductModel $productModel
    ) {}

    public function store(array $data): array
    {
        // 1. Validate model rules
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                'Validation failed',
                $this->productModel->errors()
            );
        }

        // 2. Business rules
        if ($this->isProductNameTaken($data['name'])) {
            throw new ConflictException('Product name already exists');
        }

        // 3. Process
        $productId = $this->productModel->insert($data);
        $product = $this->productModel->find($productId);

        // 4. Format response
        return ApiResponse::created($product->toArray());
    }
}
```

### Responsibilities

1. **Validate business rules** - Beyond model validation
2. **Orchestrate operations** - Coordinate multiple models/services
3. **Transform data** - Prepare for persistence or response
4. **Throw exceptions** - For all error conditions
5. **Format responses** - Use `ApiResponse::*()` methods

### Rules
- ✅ Contains ALL business logic
- ✅ Throws custom exceptions
- ✅ Returns arrays (via ApiResponse)
- ✅ Implements interface
- ❌ NO HTTP code (no Request, no Response)
- ❌ NO direct database queries (use models)

---

## Model Layer

**Location:** `app/Models/`

**Responsibility:** Database operations ONLY.

### Pattern

```php
class ProductModel extends Model
{
    use Filterable, Searchable;  // Query features

    protected $table            = 'products';
    protected $returnType       = ProductEntity::class;
    protected $allowedFields    = ['name', 'price', 'description'];
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    // Validation rules
    protected $validationRules = [
        'name' => [
            'rules'  => 'required|max_length[255]',
            'errors' => [
                'required'   => 'Name is required',
                'max_length' => 'Name too long',
            ],
        ],
        'price' => 'required|numeric|greater_than[0]',
    ];

    // Query features
    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'created_at'];
    protected array $sortableFields   = ['id', 'name', 'price'];
}
```

### Key Features

**Automatic Validation:**
```php
$id = $this->productModel->insert($data);  // Auto-validates
if (!$id) {
    $errors = $this->productModel->errors();  // Get errors
}
```

**Manual Validation:**
```php
if (!$this->productModel->validate($data)) {
    $errors = $this->productModel->errors();
}
```

**Timestamps:**
- Automatically sets `created_at` and `updated_at`

**Soft Deletes:**
- `delete()` sets `deleted_at` instead of removing row
- `find()` excludes soft-deleted by default
- `withDeleted()` includes soft-deleted

**Traits:**
- `Filterable` - Adds `applyFilters()` method
- `Searchable` - Adds `search()` method

### Rules
- ✅ Database operations via query builder
- ✅ Data validation rules
- ✅ Return entities
- ❌ NO business logic
- ❌ NO raw SQL queries

---

## Entity Layer

**Location:** `app/Entities/`

**Responsibility:** Data representation and transformation.

### Pattern

```php
class ProductEntity extends Entity
{
    // Type casting
    protected $casts = [
        'id'    => 'integer',
        'price' => 'float',
        'stock' => 'integer',
    ];

    // Date fields
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Hide sensitive fields
    protected array $hidden = ['internal_notes', 'cost'];

    // Override toArray() to hide fields
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    // Computed properties
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    // Mutators
    public function setName(string $value): self
    {
        $this->attributes['name'] = strtoupper($value);
        return $this;
    }
}
```

### Features

**Type Casting:**
```php
$product = $model->find(1);
$product->id;    // int (DB returns string)
$product->price; // float
$product->created_at; // CodeIgniter\I18n\Time
```

**Hiding Fields:**
```php
$product->toArray();  // 'internal_notes' excluded
```

**Computed Properties:**
```php
if ($product->isInStock()) {
    echo $product->getFormattedPrice();
}
```

### Rules
- ✅ Type casting
- ✅ Computed properties
- ✅ Hide sensitive fields
- ✅ Domain helper methods
- ❌ NO database operations
- ❌ NO business logic (keep it simple)

---

## Summary

| Layer | Location | Responsibility | Returns | Can Access |
|-------|----------|----------------|---------|------------|
| **Controller** | `Controllers/` | HTTP handling | `ResponseInterface` | Services |
| **Service** | `Services/` | Business logic | `array` (ApiResponse) | Models, other Services |
| **Model** | `Models/` | Database ops | Entities | Database |
| **Entity** | `Entities/` | Data representation | `self`, `array` | Own data only |

**Flow:**
```
Controller → Service → Model → Entity
     ↓          ↓        ↓        ↓
   HTTP    Business   Database  Data
  Handling   Logic   Operations Repr.
```

Each layer is **independently testable** and has **one reason to change**.
