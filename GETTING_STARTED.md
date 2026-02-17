# Getting Started with CI4 API Starter

Welcome! This guide will help you get up and running with the CI4 API Starter template in under 30 minutes.

## What is This?

This is a **production-ready REST API template** built on CodeIgniter 4 that follows enterprise-grade architectural patterns. Think of it as a solid foundation that saves you weeks of setup and lets you focus on building your business logic.

### Key Features

- 🔐 **JWT Authentication** - Secure token-based auth with refresh tokens
- 👥 **Role-Based Access** - Admin/user roles with middleware protection
- 📧 **Email System** - Verification, password reset, queue support
- 📁 **File Management** - Upload/download with S3 support
- 🔍 **Advanced Querying** - Filtering, searching, sorting, pagination
- ✅ **Comprehensive Test Suite** - Coverage across unit, integration, and feature tests
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
- PHP 8.2+ with extensions: mysqli, mbstring, intl, json
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

# 7. (Optional) Seed sample data
php spark db:seed UserSeeder

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

### Access Protected Endpoint

```bash
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## Your First CRUD: Product Management

Let's build a complete Product resource step by step. This will teach you the patterns used throughout the project.

### Step 1: Create the Migration

```bash
php spark make:migration CreateProductsTable
```

Edit the generated file in `app/Database/Migrations/`:

```php
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateProductsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'stock' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name');
        $this->forge->addKey('created_at');
        $this->forge->createTable('products');
    }

    public function down()
    {
        $this->forge->dropTable('products');
    }
}
```

Run the migration:
```bash
php spark migrate
```

### Step 2: Create the Entity

Create `app/Entities/ProductEntity.php`:

```php
<?php
namespace App\Entities;
use CodeIgniter\Entity\Entity;

class ProductEntity extends Entity
{
    protected $casts = [
        'id'    => 'integer',
        'price' => 'float',
        'stock' => 'integer',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Computed property: is product in stock?
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    // Computed property: formatted price
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }
}
```

### Step 3: Create the Model

Create `app/Models/ProductModel.php`:

```php
<?php
namespace App\Models;
use App\Entities\ProductEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

class ProductModel extends Model
{
    use Filterable, Searchable;

    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $returnType       = ProductEntity::class;
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'name',
        'description',
        'price',
        'stock',
    ];

    // Validation rules
    protected $validationRules = [
        'name' => [
            'rules'  => 'required|max_length[255]',
            'errors' => [
                'required'   => 'Product name is required',
                'max_length' => 'Product name cannot exceed 255 characters',
            ],
        ],
        'price' => [
            'rules'  => 'required|numeric|greater_than[0]',
            'errors' => [
                'required'     => 'Price is required',
                'numeric'      => 'Price must be a number',
                'greater_than' => 'Price must be greater than 0',
            ],
        ],
        'stock' => [
            'rules'  => 'permit_empty|integer|greater_than_equal_to[0]',
        ],
    ];

    // Query features
    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'stock', 'created_at'];
    protected array $sortableFields   = ['id', 'name', 'price', 'stock', 'created_at'];
}
```

### Step 4: Create the Service Interface

Create `app/Interfaces/ProductServiceInterface.php`:

```php
<?php
namespace App\Interfaces;

interface ProductServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}
```

### Step 5: Create the Service

Create `app/Services/ProductService.php`:

```php
<?php
namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\ProductServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\ProductModel;

class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductModel $productModel
    ) {}

    public function index(array $data): array
    {
        $builder = new QueryBuilder($this->productModel);

        // Apply filters
        if (!empty($data['filter'])) {
            $builder->filter($data['filter']);
        }

        // Apply search
        if (!empty($data['search'])) {
            $builder->search($data['search']);
        }

        // Apply sorting
        if (!empty($data['sort'])) {
            $builder->sort($data['sort']);
        }

        // Paginate
        $page = max((int) ($data['page'] ?? 1), 1);
        $limit = (int) ($data['limit'] ?? 20);
        $result = $builder->paginate($page, $limit);

        // Convert entities to arrays
        $result['data'] = array_map(fn($product) => $product->toArray(), $result['data']);

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['perPage']
        );
    }

    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException('Product ID is required');
        }

        $product = $this->productModel->find($data['id']);

        if (!$product) {
            throw new NotFoundException('Product not found');
        }

        return ApiResponse::success($product->toArray());
    }

    public function store(array $data): array
    {
        // Validate
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                'Validation failed',
                $this->productModel->errors()
            );
        }

        // Insert
        $productId = $this->productModel->insert($data);
        $product = $this->productModel->find($productId);

        return ApiResponse::created($product->toArray(), 'Product created successfully');
    }

    public function update(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException('Product ID is required');
        }

        $product = $this->productModel->find($data['id']);

        if (!$product) {
            throw new NotFoundException('Product not found');
        }

        // Validate
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                'Validation failed',
                $this->productModel->errors()
            );
        }

        // Update
        $this->productModel->update($data['id'], $data);
        $product = $this->productModel->find($data['id']);

        return ApiResponse::success($product->toArray(), 'Product updated successfully');
    }

    public function destroy(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException('Product ID is required');
        }

        $product = $this->productModel->find($data['id']);

        if (!$product) {
            throw new NotFoundException('Product not found');
        }

        // Soft delete
        $this->productModel->delete($data['id']);

        return ApiResponse::deleted('Product deleted successfully');
    }
}
```

### Step 6: Register the Service

Add to `app/Config/Services.php`:

```php
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

### Step 7: Create the Controller

Create `app/Controllers/Api/V1/ProductController.php`:

```php
<?php
namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;

class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    // That's it! All CRUD methods are inherited from ApiController:
    // - index()   -> GET /products
    // - show($id) -> GET /products/:id
    // - create()  -> POST /products
    // - update($id) -> PUT /products/:id
    // - delete($id) -> DELETE /products/:id
}
```

### Step 8: Add Routes

Add to `app/Config/Routes.php`:

```php
$routes->group('api/v1', ['filter' => 'jwtauth'], function ($routes) {
    // Public read access
    $routes->get('products', 'Api\V1\ProductController::index');
    $routes->get('products/(:num)', 'Api\V1\ProductController::show/$1');

    // Admin-only write access
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('products', 'Api\V1\ProductController::create');
        $routes->put('products/(:num)', 'Api\V1\ProductController::update/$1');
        $routes->delete('products/(:num)', 'Api\V1\ProductController::delete/$1');
    });
});
```

### Step 9: Test Your Endpoints

```bash
# Create a product (requires admin token)
curl -X POST http://localhost:8080/api/v1/products \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop",
    "description": "High-performance laptop",
    "price": 999.99,
    "stock": 50
  }'

# List products (with filtering and search)
curl -X GET "http://localhost:8080/api/v1/products?search=laptop&filter[price][gte]=500&sort=-created_at&page=1&limit=10" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Get single product
curl -X GET http://localhost:8080/api/v1/products/1 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Update product
curl -X PUT http://localhost:8080/api/v1/products/1 \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Gaming Laptop",
    "price": 1299.99,
    "stock": 30
  }'

# Delete product (soft delete)
curl -X DELETE http://localhost:8080/api/v1/products/1 \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN"
```

---

## Understanding the Pattern

What you just built follows these patterns:

1. **Request flows through layers**: Controller → Service → Model → Entity
2. **Controller is thin**: Only handles HTTP, delegates to service
3. **Service contains business logic**: Validation, error handling, orchestration
4. **Model handles database**: Query builder operations only
5. **Entity represents data**: Type casting, computed properties
6. **Exceptions for errors**: Throw custom exceptions, controller handles them
7. **ApiResponse for consistency**: All responses follow same structure

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
