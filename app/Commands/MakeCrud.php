<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * MakeCrud Command (Modernized)
 *
 * Generates a complete CRUD resource following the Domain-Driven DTO Architecture.
 */
class MakeCrud extends BaseCommand
{
    protected $group = 'Scaffold';
    protected $name = 'make:crud';
    protected $description = 'Generate CRUD skeleton files following the domain-driven architecture.';
    protected $usage = 'make:crud <Resource> [--domain <Domain>] [--route <slug>] [--public-read <yes|no>] [--admin-write <yes|no>] [--soft-delete <yes|no>]';
    protected $arguments = [
        'Resource' => 'Resource singular name (e.g. Product, InvoiceItem)',
    ];
    protected $options = [
        '--domain' => 'Domain folder (default: Catalog)',
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
            APPPATH . "Interfaces/{$domain}/{$resource}ServiceInterface.php" => $this->interfaceTemplate($resource, $domain),
            APPPATH . "Services/{$domain}/{$resource}Service.php" => $this->serviceTemplate($resource, $resourceLower, $domain),
            APPPATH . "Controllers/Api/V1/{$domain}/{$resource}Controller.php" => $this->controllerTemplate($resource, $resourceLower, $domain),
            APPPATH . "Documentation/{$domain}/{$resource}Endpoints.php" => $this->docEndpointsTemplate($resource, $route, $domain),
            APPPATH . "Language/en/{$resourcePlural}.php" => $this->langTemplate($resource, false),
            APPPATH . "Language/es/{$resourcePlural}.php" => $this->langTemplate($resource, true),
            ROOTPATH . "tests/Unit/Services/{$domain}/{$resource}ServiceTest.php" => $this->unitTestTemplate($resource, $domain),
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

        $this->registerServiceMethod($resource, $resourceLower, $domain);

        CLI::newLine();
        CLI::write('Next steps:', 'cyan');
        CLI::write("1) Add specific validation rules to DTOs in app/DTO/Request/{$domain}/");
        CLI::write("2) Define your DB schema in a new migration.");
        CLI::write("3) Add routes in app/Config/Routes/v1/" . strtolower($domain) . ".php with the following snippet:");
        CLI::write("4) Run module bootstrap validation: php spark module:check {$resource} --domain {$domain}");
        CLI::newLine();

        $routePrefix = "\$routes->group('{$route}', ['namespace' => '\\\\App\\\\Controllers\\\\Api\\\\V1\\\\{$domain}'], function (\$routes) {";
        CLI::write($routePrefix, 'yellow');
        if ($publicRead) {
            CLI::write("    \$routes->group('', ['filter' => ['jwtauth', 'throttle']], function (\$routes) {", 'yellow');
            CLI::write("        \$routes->get('', '{$resource}Controller::index');", 'yellow');
            CLI::write("        \$routes->get('(:num)', '{$resource}Controller::show/$1');", 'yellow');
            CLI::write("    });", 'yellow');
        }
        if ($adminWrite) {
            CLI::write("    \$routes->group('', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function (\$routes) {", 'yellow');
            if (!$publicRead) {
                CLI::write("        \$routes->get('', '{$resource}Controller::index');", 'yellow');
                CLI::write("        \$routes->get('(:num)', '{$resource}Controller::show/$1');", 'yellow');
            }
            CLI::write("        \$routes->post('', '{$resource}Controller::create');", 'yellow');
            CLI::write("        \$routes->put('(:num)', '{$resource}Controller::update/$1');", 'yellow');
            CLI::write("        \$routes->delete('(:num)', '{$resource}Controller::delete/$1');", 'yellow');
            CLI::write("    });", 'yellow');
        }
        CLI::write("});", 'yellow');

        return EXIT_SUCCESS;
    }

    private function registerServiceMethod(string $resource, string $resourceLower, string $domain): void
    {
        $domainTrait = "{$domain}DomainServices";
        $servicesPath = APPPATH . "Config/{$domainTrait}.php";

        // If domain-specific trait doesn't exist, fallback to main Services.php
        if (!file_exists($servicesPath)) {
            $servicesPath = APPPATH . 'Config/Services.php';
        }

        $content = (string) file_get_contents($servicesPath);
        $methodName = "{$resourceLower}Service";

        if (str_contains($content, "function {$methodName}(")) {
            CLI::write("Skipped service registration: method already exists in {$servicesPath}.", 'yellow');
            return;
        }

        $mapperMethodName = "{$resourceLower}ResponseMapper";

        $method = "\n    public static function {$mapperMethodName}(bool \$getShared = true)\n    {\n        if (\$getShared) {\n            return static::getSharedInstance('{$mapperMethodName}');\n        }\n\n        return new \\App\\Services\\Core\\Mappers\\DtoResponseMapper(\n            \\App\\DTO\\Response\\{$domain}\\{$resource}ResponseDTO::class\n        );\n    }\n\n    public static function {$methodName}(bool \$getShared = true)\n    {\n        if (\$getShared) {\n            return static::getSharedInstance('{$methodName}');\n        }\n\n        return new \\App\\Services\\{$domain}\\{$resource}Service(\n            new \\App\\Repositories\\GenericRepository(model(\\App\\Models\\{$resource}Model::class)),\n            static::{$mapperMethodName}()\n        );\n    }\n";

        $needle = "\n}\n";
        $position = strrpos($content, $needle);

        if ($position !== false) {
            $updated = substr($content, 0, $position) . $method . substr($content, $position);
            file_put_contents($servicesPath, $updated);
            CLI::write("Updated: {$servicesPath}", 'green');
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
use App\Traits\Filterable;
use App\Traits\Searchable;

class {$resource}Model extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

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
    public int \$per_page;
    public ?string \$search;

    public function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
        ];
    }

    protected function map(array \$data): void
    {
        \$this->page = isset(\$data['page']) ? (int) \$data['page'] : 1;
        \$this->per_page = isset(\$data['per_page']) ? (int) \$data['per_page'] : 20;
        \$this->search = \$data['search'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'page' => \$this->page,
            'per_page' => \$this->per_page,
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

    public function rules(): array
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

    public function rules(): array
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

namespace App\Interfaces\\{$domain};

use App\Interfaces\Core\CrudServiceContract;

interface {$resource}ServiceInterface extends CrudServiceContract
{
    // Add resource-specific service methods here if needed.
}
PHP;
    }

    private function serviceTemplate(string $resource, string $resourceLower, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Services\\{$domain};

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\\{$domain}\\{$resource}ServiceInterface;
use App\Services\Core\BaseCrudService;

class {$resource}Service extends BaseCrudService implements {$resource}ServiceInterface
{
    public function __construct(
        RepositoryInterface \${$resourceLower}Repository,
        ResponseMapperInterface \$responseMapper
    ) {
        parent::__construct(\${$resourceLower}Repository, \$responseMapper);
    }

    /**
     * Domain Hooks
     * 
     * Implement beforeStore, afterStore, etc., to add specific business logic
     * while keeping the service layer clean and DRY.
     */
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
use App\Traits\Controllers\HasCrudActions;
use Config\Services;

class {$resource}Controller extends ApiController
{
    use HasCrudActions;

    protected function resolveDefaultService(): object
    {
        return Services::{$resourceLower}Service();
    }

    protected string \$indexDto = {$resource}IndexRequestDTO::class;
    protected string \$createDto = {$resource}CreateRequestDTO::class;
    protected string \$updateDto = {$resource}UpdateRequestDTO::class;

    protected array \$statusCodes = [
        'store' => 201,
    ];
}
PHP;
    }

    private function docEndpointsTemplate(string $resource, string $route, string $domain): string
    {
        $plural = $this->pluralize($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Documentation\\{$domain};

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

namespace Tests\Unit\Services\\{$domain};

use App\Services\\{$domain}\\{$resource}Service;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

class {$resource}ServiceTest extends CIUnitTestCase
{
    public function testIndexContractReturnsDataTransferObjectInterface(): void
    {
        \$method = new ReflectionMethod({$resource}Service::class, 'index');
        \$returnType = \$method->getReturnType();
        \$this->assertNotNull(\$returnType);
        \$this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, \$returnType?->getName());
    }

    public function testStoreAndUpdateSignaturesUseDtoAndSecurityContext(): void
    {
        \$store = new ReflectionMethod({$resource}Service::class, 'store');
        \$update = new ReflectionMethod({$resource}Service::class, 'update');

        \$storeParams = \$store->getParameters();
        \$updateParams = \$update->getParameters();
        \$storeContextType = (string) \$storeParams[1]->getType();
        \$updateContextType = (string) \$updateParams[2]->getType();

        \$this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, (string) \$storeParams[0]->getType());
        \$this->assertSame(\App\DTO\SecurityContext::class, ltrim(\$storeContextType, '?'));
        \$this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, (string) \$updateParams[1]->getType());
        \$this->assertSame(\App\DTO\SecurityContext::class, ltrim(\$updateContextType, '?'));
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

class {$resource}ModelTest extends CIUnitTestCase
{
    public function testModelClassCanBeInstantiated(): void
    {
        \$model = model(\\\\App\\\\Models\\\\{$resource}Model::class);

        \$this->assertInstanceOf(\\\\CodeIgniter\\\\Model::class, \$model);
    }
}
PHP;
    }

    private function featureTestTemplate(string $resource, string $route, string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\\{$domain};

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class {$resource}ControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testControllerRouteReferenceExists(): void
    {
        \$routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        \$modularDir = APPPATH . 'Config/Routes/v1';
        \$files = is_dir(\$modularDir) ? (glob(\$modularDir . '/*.php') ?: []) : [];
        foreach (\$files as \$file) {
            \$routes .= (string) file_get_contents(\$file);
        }

        \$this->assertStringContainsString('{$resource}Controller::', \$routes);
    }
}
PHP;
    }
}
