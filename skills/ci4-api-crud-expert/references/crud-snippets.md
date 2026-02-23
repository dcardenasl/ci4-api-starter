# CRUD Snippets (mínimos)

## Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;

class ProductController extends ApiController
{
    protected string $serviceName = 'productService';
}
```

## Interface

```php
<?php

declare(strict_types=1);

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

## Service (estructura)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\ProductServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\ProductModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ValidatesRequiredFields;

class ProductService implements ProductServiceInterface
{
    use AppliesQueryOptions;
    use ValidatesRequiredFields;

    public function __construct(protected ProductModel $productModel)
    {
    }

    public function index(array $data): array
    {
        $builder = new QueryBuilder($this->productModel);
        $this->applyQueryOptions($builder, $data);
        [$page, $limit] = $this->resolvePagination($data, (int) env('PAGINATION_DEFAULT_LIMIT', 20));
        $result = $builder->paginate($page, $limit);
        $result['data'] = array_map(fn ($item) => $item->toArray(), $result['data']);

        return ApiResponse::paginated($result['data'], $result['total'], $result['page'], $result['perPage']);
    }

    public function show(array $data): array
    {
        $id = $this->validateRequiredId($data);
        $item = $this->productModel->find($id);
        if (! $item) {
            throw new NotFoundException(lang('Products.notFound'));
        }
        return ApiResponse::success($item->toArray());
    }
}
```

## Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ProductEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

class ProductModel extends Model
{
    use Filterable;
    use Searchable;

    protected $table = 'products';
    protected $returnType = ProductEntity::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'description'];
    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'created_at'];
    protected array $sortableFields = ['id', 'name', 'created_at'];
}
```

## Services.php

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

## Routes

```php
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:num)', 'ProductController::show/$1');

    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('products', 'ProductController::create');
        $routes->put('products/(:num)', 'ProductController::update/$1');
        $routes->delete('products/(:num)', 'ProductController::delete/$1');
    });
});
```
