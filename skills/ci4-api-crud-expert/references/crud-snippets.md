# CRUD Snippets (Arquitectura Modernizada)

## Controller (Declarativo)

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Catalog;

use App\Controllers\ApiController;
use App\DTO\Request\Catalog\ProductIndexRequestDTO;
use App\DTO\Request\Catalog\ProductCreateRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ProductIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', ProductCreateRequestDTO::class);
    }
}
```

## Interface (Tipada)

```php
<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Interfaces\DataTransferObjectInterface;

interface ProductServiceInterface
{
    public function index(DataTransferObjectInterface $request): array;
    public function show(int $id): DataTransferObjectInterface;
    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface;
}
```

## Request DTO (Autovalidado)

```php
<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class ProductCreateRequestDTO extends BaseRequestDTO
{
    public string $name;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
```

## Service (Heredado y Transaccional)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\ProductServiceInterface;
use App\Models\ProductModel;
use App\Traits\AppliesQueryOptions;

class ProductService extends BaseCrudService implements ProductServiceInterface
{
    use AppliesQueryOptions;

    protected string $responseDtoClass = \App\DTO\Response\Catalog\ProductResponseDTO::class;

    public function __construct(protected ProductModel $productModel) {
        $this->model = $productModel;
    }

    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function() use ($request) {
            $id = $this->model->insert($request->toArray());
            // Lógica de negocio adicional...
            return $this->mapToResponse($this->model->find($id));
        });
    }
}
```
