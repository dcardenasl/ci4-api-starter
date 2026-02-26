<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * MakeCrud Command
 *
 * Generates a complete CRUD resource following the Modernized DTO-First Architecture.
 */
class MakeCrud extends BaseCommand
{
    protected $group = 'Scaffold';
    protected $name = 'make:crud';
    protected $description = 'Generate CRUD skeleton files following the modernized DTO-first architecture.';
    protected $usage = 'make:crud <Resource> [--domain <Domain>] [--route <slug>] [--public-read <yes|no>] [--admin-write <yes|no>] [--soft-delete <yes|no>]';
    protected $arguments = [
        'Resource' => 'Resource singular name (e.g. Product, InvoiceItem)',
    ];
    protected $options = [
        '--domain' => 'Controller and DTO domain folder (default: Catalog)',
        '--route' => 'Route slug plural (default: kebab-case plural of resource)',
        '--public-read' => 'Generate public read routes snippet yes|no (default: yes)',
        '--admin-write' => 'Generate admin write routes snippet yes|no (default: yes)',
        '--soft-delete' => 'Model soft delete yes|no (default: yes)',
    ];

    public function run(array $params)
    {
        $resourceInput = (string) ($params[0] ?? '');

        if ($resourceInput === '') {
            CLI::error('Resource argument is required. Example: php spark make:crud Product');
            return EXIT_ERROR;
        }

        $resource = $this->studly($resourceInput);
        $resourcePlural = $this->pluralize($resource);
        $resourceLower = lcfirst($resource);
        $resourcePluralLower = lcfirst($resourcePlural);

        $domain = $this->studly((string) (CLI::getOption('domain') ?: 'Catalog'));
        $route = (string) (CLI::getOption('route') ?: $this->toKebab($resourcePlural));
        $publicRead = $this->yesNoOption('public-read', true);
        $adminWrite = $this->yesNoOption('admin-write', true);
        $softDelete = $this->yesNoOption('soft-delete', true);

        $files = [
            APPPATH . "Entities/{$resource}Entity.php" => $this->entityTemplate($resource),
            APPPATH . "Models/{$resource}Model.php" => $this->modelTemplate($resource, $resourcePluralLower, $softDelete),
            APPPATH . "DTO/Request/{$domain}/{$resource}IndexRequestDTO.php" => $this->indexRequestDtoTemplate($resource, $domain),
            APPPATH . "DTO/Request/{$domain}/{$resource}CreateRequestDTO.php" => $this->createRequestDtoTemplate($resource, $domain),
            APPPATH . "DTO/Request/{$domain}/{$resource}UpdateRequestDTO.php" => $this->updateRequestDtoTemplate($resource, $domain),
            APPPATH . "DTO/Response/{$domain}/{$resource}ResponseDTO.php" => $this->responseDtoTemplate($resource, $domain),
            APPPATH . "Interfaces/{$resource}ServiceInterface.php" => $this->interfaceTemplate($resource, $domain),
            APPPATH . "Services/{$resource}Service.php" => $this->serviceTemplate($resource, $resourceLower, $domain),
            APPPATH . "Controllers/Api/V1/{$domain}/{$resource}Controller.php" => $this->controllerTemplate($resource, $resourceLower, $domain),
            APPPATH . "Documentation/{$resourcePlural}/{$resource}Endpoints.php" => $this->docEndpointsTemplate($resource, $route, $domain),
            APPPATH . "Language/en/{$resourcePlural}.php" => $this->langTemplate($resource, false),
            APPPATH . "Language/es/{$resourcePlural}.php" => $this->langTemplate($resource, true),
            ROOTPATH . "tests/Unit/Services/{$resource}ServiceTest.php" => $this->unitTestTemplate($resource, $domain),
            ROOTPATH . "tests/Integration/Models/{$resource}ModelTest.php" => $this->integrationTestTemplate($resource),
            ROOTPATH . "tests/Feature/Controllers/{$domain}/{$resource}ControllerTest.php" => $this->featureTestTemplate($resource, $route, $domain),
        ];

        foreach ($files as $path => $content) {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                CLI::error("Failed to create directory: {$dir}");
                return EXIT_ERROR;
            }

            if (file_exists($path)) {
                CLI::error("File already exists: {$path}");
                return EXIT_ERROR;
            }
        }

        foreach ($files as $path => $content) {
            file_put_contents($path, $content);
            CLI::write("Created: {$path}", 'green');
        }

        $this->registerServiceMethod($resource, $resourceLower);

        CLI::newLine();
        CLI::write('Next steps:', 'cyan');
        CLI::write("1) Add specific validation rules to DTOs in app/DTO/Request/{$domain}/");
        CLI::write("2) Define your DB schema in a new migration.");
        CLI::write("3) Update app/Config/Routes.php with the following snippet:");
        CLI::newLine();

        $routePrefix = "\$routes->group('{$route}', ['namespace' => 'App\\Controllers\\Api\\V1\\{$domain}'], function(\$routes) {";
        CLI::write($routePrefix, 'yellow');
        if ($publicRead) {
            CLI::write("    \$routes->get('', '{$resource}Controller::index');", 'yellow');
            CLI::write("    \$routes->get('(:num)', '{$resource}Controller::show/$1');", 'yellow');
        }
        if ($adminWrite) {
            CLI::write("    \$routes->group('', ['filter' => 'roleauth:admin'], function(\$routes) {", 'yellow');
            CLI::write("        \$routes->post('', '{$resource}Controller::create');", 'yellow');
            CLI::write("        \$routes->put('(:num)', '{$resource}Controller::update/$1');", 'yellow');
            CLI::write("        \$routes->delete('(:num)', '{$resource}Controller::delete/$1');", 'yellow');
            CLI::write("    });", 'yellow');
        }
        CLI::write("});", 'yellow');

        return EXIT_SUCCESS;
    }

    private function registerServiceMethod(string $resource, string $resourceLower): void
    {
        $servicesPath = APPPATH . 'Config/Services.php';
        if (!file_exists($servicesPath)) {
            return;
        }

        $content = (string) file_get_contents($servicesPath);
        $methodName = "{$resourceLower}Service";

        if (str_contains($content, "function {$methodName}(")) {
            CLI::write('Skipped service registration: method already exists.', 'yellow');
            return;
        }

        $method = "\n    public static function {$methodName}(bool \$getShared = true)\n    {\n        if (\$getShared) {\n            return static::getSharedInstance('{$methodName}');\n        }\n\n        return new \\App\\Services\\{$resource}Service(\n            new \\App\\Models\\{$resource}Model()\n        );\n    }\n";

        $needle = "\n}\n";
        $position = strrpos($content, $needle);

        if ($position !== false) {
            $updated = substr($content, 0, $position) . $method . substr($content, $position);
            file_put_contents($servicesPath, $updated);
            CLI::write('Updated: app/Config/Services.php', 'green');
        }
    }

    private function yesNoOption(string $name, bool $default): bool
    {
        $raw = CLI::getOption($name);
        if ($raw === null || $raw === true) {
            return $default;
        }

        $value = strtolower((string) $raw);
        return in_array($value, ['yes', 'y', 'true', '1'], true);
    }

    private function studly(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', trim($value)) ?? '';
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $parts = array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts);

        return implode('', $parts);
    }

    private function pluralize(string $value): string
    {
        if (preg_match('/y$/i', $value)) {
            return preg_replace('/y$/i', 'ies', $value) ?? ($value . 's');
        }
        if (preg_match('/(s|x|z|ch|sh)$/i', $value)) {
            return $value . 'es';
        }

        return $value . 's';
    }

    private function toKebab(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;
        return strtolower($value);
    }

    private function entityTemplate(string $resource): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class {$resource}Entity extends Entity
{
    protected array \$casts = [
        'id' => 'integer',
    ];

    protected array \$dates = ['created_at', 'updated_at', 'deleted_at'];
}
PHP;
    }

    private function modelTemplate(string $resource, string $table, bool $softDelete): string
    {
        $soft = $softDelete ? 'true' : 'false';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\{$resource}Entity;
use App\Traits\Auditable;
use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

class {$resource}Model extends Model
{
    use Filterable;
    use Searchable;
    use Auditable;

    protected \$table = '{$table}';
    protected \$primaryKey = 'id';
    protected \$returnType = {$resource}Entity::class;
    protected \$useSoftDeletes = {$soft};
    protected \$useTimestamps = true;
    protected \$allowedFields = [
        'name',
    ];

    protected array \$searchableFields = ['name'];
    protected array \$filterableFields = ['id', 'name', 'created_at'];
    protected array \$sortableFields = ['id', 'name', 'created_at'];

    protected \$validationRules = [
        'name' => [
            'rules' => 'required|max_length[255]',
            'errors' => [
                'required' => 'InputValidation.common.nameRequired',
                'max_length' => 'InputValidation.common.nameMaxLength',
            ],
        ],
    ];
}
PHP;
    }

    private function indexRequestDtoTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$resource}IndexRequestDTO extends BaseRequestDTO
{
    public int \$page;
    public int \$perPage;
    public ?string \$search;

    protected function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
        ];
    }

    protected function map(array \$data): void
    {
        \$this->page = isset(\$data['page']) ? (int) \$data['page'] : 1;
        \$this->perPage = isset(\$data['per_page']) ? (int) \$data['per_page'] : 20;
        \$this->search = \$data['search'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'page' => \$this->page,
            'per_page' => \$this->perPage,
            'search' => \$this->search,
        ];
    }
}
PHP;
    }

    private function createRequestDtoTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$resource}CreateRequestDTO extends BaseRequestDTO
{
    public string \$name;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max_length[255]',
        ];
    }

    protected function map(array \$data): void
    {
        \$this->name = (string) (\$data['name'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'name' => \$this->name,
        ];
    }
}
PHP;
    }

    private function updateRequestDtoTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$resource}UpdateRequestDTO extends BaseRequestDTO
{
    public ?string \$name;

    protected function rules(): array
    {
        return [
            'name' => 'permit_empty|string|max_length[255]',
        ];
    }

    protected function map(array \$data): void
    {
        \$this->name = isset(\$data['name']) ? (string) \$data['name'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => \$this->name,
        ], fn(\$v) => \$v !== null);
    }
}
PHP;
    }

    private function responseDtoTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Response\\{$domain};

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: '{$resource}Response',
    title: '{$resource} Response',
    required: ['id', 'name']
)]
readonly class {$resource}ResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique identifier', example: 1)]
        public int \$id,

        #[OA\Property(description: 'Resource name', example: 'Example Name')]
        public string \$name,

        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string \$createdAt = null
    ) {}

    public static function fromArray(array \$data): self
    {
        \$createdAt = \$data['created_at'] ?? null;
        if (\$createdAt instanceof \DateTimeInterface) {
            \$createdAt = \$createdAt->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) (\$data['id'] ?? 0),
            name: (string) (\$data['name'] ?? ''),
            createdAt: \$createdAt ? (string) \$createdAt : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => \$this->id,
            'name' => \$this->name,
            'created_at' => \$this->createdAt,
        ];
    }
}
PHP;
    }

    private function interfaceTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Interfaces\DataTransferObjectInterface;

interface {$resource}ServiceInterface
{
    public function index(DataTransferObjectInterface \$request): array;
    public function show(int \$id): DataTransferObjectInterface;
    public function store(DataTransferObjectInterface \$request): DataTransferObjectInterface;
    public function update(int \$id, DataTransferObjectInterface \$request): DataTransferObjectInterface;
    public function destroy(int \$id): array;
}
PHP;
    }

    private function serviceTemplate(string $resource, string $resourceLower, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\{$resource}ServiceInterface;
use App\Models\\{$resource}Model;
use App\Traits\AppliesQueryOptions;

class {$resource}Service extends BaseCrudService implements {$resource}ServiceInterface
{
    use AppliesQueryOptions;

    protected string \$responseDtoClass = \App\DTO\Response\\{$domain}\\{$resource}ResponseDTO::class;

    public function __construct(
        protected {$resource}Model \${$resourceLower}Model
    ) {
        \$this->model = \${$resourceLower}Model;
    }

    public function store(DataTransferObjectInterface \$request): DataTransferObjectInterface
    {
        return \$this->wrapInTransaction(function() use (\$request) {
            \$id = \$this->model->insert(\$request->toArray());
            if (!\$id) {
                throw new \App\Exceptions\ValidationException(lang('Api.validationFailed'), \$this->model->errors());
            }
            return \$this->mapToResponse(\$this->model->find(\$id));
        });
    }

    public function update(int \$id, DataTransferObjectInterface \$request): DataTransferObjectInterface
    {
        return \$this->wrapInTransaction(function() use (\$id, \$request) {
            if (!\$this->model->find(\$id)) {
                throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
            }
            
            \$data = \$request->toArray();
            if (empty(\$data)) {
                throw new BadRequestException(lang('Api.invalidRequest'));
            }

            \$this->model->update(\$id, \$data);
            return \$this->mapToResponse(\$this->model->find(\$id));
        });
    }
}
PHP;
    }

    private function controllerTemplate(string $resource, string $resourceLower, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\\{$domain};

use App\Controllers\ApiController;
use App\DTO\Request\\{$domain}\\{$resource}IndexRequestDTO;
use App\DTO\Request\\{$domain}\\{$resource}CreateRequestDTO;
use App\DTO\Request\\{$domain}\\{$resource}UpdateRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

class {$resource}Controller extends ApiController
{
    protected string \$serviceName = '{$resourceLower}Service';

    public function index(): ResponseInterface
    {
        return \$this->handleRequest('index', {$resource}IndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return \$this->handleRequest('store', {$resource}CreateRequestDTO::class);
    }

    public function update(int \$id): ResponseInterface
    {
        return \$this->handleRequest(
            fn(\$dto) => \$this->getService()->update(\$id, \$dto),
            {$resource}UpdateRequestDTO::class
        );
    }

    public function show(int \$id): ResponseInterface
    {
        return \$this->handleRequest(fn() => \$this->getService()->show(\$id));
    }

    public function delete(int \$id): ResponseInterface
    {
        return \$this->handleRequest(fn() => \$this->getService()->destroy(\$id));
    }
}
PHP;
    }

    private function docEndpointsTemplate(string $resource, string $route, string $domain): string
    {
        $plural = $this->pluralize($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Documentation\\{$plural};

use OpenApi\Attributes as OA;

/**
 * OpenAPI placeholders for {$resource} endpoints.
 *
 * @OA\Tag(name="{$plural}", description="{$resource} management")
 */
class {$resource}Endpoints
{
    #[OA\Get(
        path: '/api/v1/{$route}',
        tags: ['{$plural}'],
        summary: 'List {$plural}',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/{$resource}Response')
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index() {}
}
PHP;
    }

    private function langTemplate(string $resource, bool $spanish): string
    {
        $singular = strtolower($resource);
        $created = $spanish ? ucfirst($singular) . ' creado exitosamente' : $resource . ' created successfully';
        $updated = $spanish ? ucfirst($singular) . ' actualizado exitosamente' : $resource . ' updated successfully';
        $deleted = $spanish ? ucfirst($singular) . ' eliminado exitosamente' : $resource . ' deleted successfully';
        $notFound = $spanish ? ucfirst($singular) . ' no encontrado' : $resource . ' not found';

        return <<<PHP
<?php

return [
    'notFound' => '{$notFound}',
    'createdSuccess' => '{$created}',
    'updatedSuccess' => '{$updated}',
    'deletedSuccess' => '{$deleted}',
];
PHP;
    }

    private function unitTestTemplate(string $resource, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\\{$domain}\\{$resource}IndexRequestDTO;
use App\Models\\{$resource}Model;
use App\Services\\{$resource}Service;
use CodeIgniter\Test\CIUnitTestCase;

class {$resource}ServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \$this->mockModel = \$this->createMock({$resource}Model::class);
        \$this->service = new {$resource}Service(\$this->mockModel);
    }

    public function testIndexReturnsData(): void
    {
        \$dto = new {$resource}IndexRequestDTO(['page' => 1]);
        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function integrationTestTemplate(string $resource): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class {$resource}ModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected \$migrate = true;
    protected \$namespace = 'App';

    public function testPlaceholder(): void
    {
        \$this->markTestIncomplete('Implement {$resource}Model integration tests.');
    }
}
PHP;
    }

    private function featureTestTemplate(string $resource, string $route, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class {$resource}ControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected \$migrate = true;
    protected \$namespace = 'App';

    public function testPlaceholder(): void
    {
        \$this->markTestIncomplete('Implement {$resource} feature tests for /api/v1/{$route}.');
    }
}
PHP;
    }
}
