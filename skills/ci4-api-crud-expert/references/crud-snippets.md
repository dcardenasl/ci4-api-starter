# CRUD Snippets (Arquitectura Millonaria)

## Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\DTO\Request\Catalog\ProductIndexRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    public function index(): ResponseInterface
    {
        $dto = $this->getDTO(ProductIndexRequestDTO::class);
        return $this->handleRequest(fn() => $this->getService()->index($dto));
    }
}
```

## Interface

```php
<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Catalog\ProductIndexRequestDTO;
use App\DTO\Response\Catalog\ProductResponseDTO;

interface ProductServiceInterface
{
    public function index(ProductIndexRequestDTO $request): array;
    public function show(array $data): ProductResponseDTO;
}
```

## Request DTO

```php
<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\Interfaces\DataTransferObjectInterface;

readonly class ProductCreateRequestDTO implements DataTransferObjectInterface
{
    public string $name;

    public function __construct(array $data)
    {
        validateOrFail($data, 'product', 'store');
        $this->name = (string) $data['name'];
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
```

## Response DTO

```php
<?php

declare(strict_types=1);

namespace App\DTO\Response\Catalog;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ProductResponse')]
readonly class ProductResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property] public int $id,
        #[OA\Property] public string $name
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) $data['name']
        );
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
```

## Service (Pure Logic)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Request\Catalog\ProductIndexRequestDTO;
use App\DTO\Response\Catalog\ProductResponseDTO;
use App\Exceptions\NotFoundException;
use App\Interfaces\ProductServiceInterface;
use App\Models\ProductModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ValidatesRequiredFields;

class ProductService implements ProductServiceInterface
{
    use AppliesQueryOptions, ValidatesRequiredFields;

    public function __construct(protected ProductModel $productModel) {}

    public function index(ProductIndexRequestDTO $request): array
    {
        $builder = new \App\Libraries\Query\QueryBuilder($this->productModel);
        $this->applyQueryOptions($builder, $request->toArray());
        
        $result = $builder->paginate($request->page, $request->perPage);
        
        $result['data'] = array_map(
            fn($item) => ProductResponseDTO::fromArray($item->toArray()), 
            $result['data']
        );

        return $result;
    }
}
```
