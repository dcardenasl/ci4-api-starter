<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MakeCrud extends BaseCommand
{
    protected $group = 'Scaffold';
    protected $name = 'make:crud';
    protected $description = 'Generate CRUD skeleton files following project conventions.';
    protected $usage = 'make:crud <Resource> [--domain <Domain>] [--route <slug>] [--public-read <yes|no>] [--admin-write <yes|no>] [--soft-delete <yes|no>]';
    protected $arguments = [
        'Resource' => 'Resource singular name (e.g. Product, InvoiceItem)',
    ];
    protected $options = [
        '--domain' => 'Controller domain folder under Api/V1 (default: Users)',
        '--route' => 'Route slug plural (default: inferred from resource)',
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

        $domain = $this->studly((string) (CLI::getOption('domain') ?: 'Users'));
        $route = (string) (CLI::getOption('route') ?: $this->toKebab($resourcePlural));
        $publicRead = $this->yesNoOption('public-read', true);
        $adminWrite = $this->yesNoOption('admin-write', true);
        $softDelete = $this->yesNoOption('soft-delete', true);

        $files = [
            APPPATH . "Entities/{$resource}Entity.php" => $this->entityTemplate($resource),
            APPPATH . "Models/{$resource}Model.php" => $this->modelTemplate($resource, $resourcePluralLower, $softDelete),
            APPPATH . "Interfaces/{$resource}ServiceInterface.php" => $this->interfaceTemplate($resource),
            APPPATH . "Services/{$resource}Service.php" => $this->serviceTemplate($resource, $resourceLower),
            APPPATH . "Validations/{$resource}Validation.php" => $this->validationTemplate($resource),
            APPPATH . "Controllers/Api/V1/{$domain}/{$resource}Controller.php" => $this->controllerTemplate($resource, $resourceLower, $domain),
            APPPATH . "Documentation/{$resourcePlural}/{$resource}Schema.php" => $this->docSchemaTemplate($resource),
            APPPATH . "Documentation/{$resourcePlural}/{$resource}Endpoints.php" => $this->docEndpointsTemplate($resource, $route),
            APPPATH . "Language/en/{$resourcePlural}.php" => $this->langTemplate($resource, false),
            APPPATH . "Language/es/{$resourcePlural}.php" => $this->langTemplate($resource, true),
            ROOTPATH . "tests/Unit/Services/{$resource}ServiceTest.php" => $this->unitTestTemplate($resource),
            ROOTPATH . "tests/Integration/Models/{$resource}ModelTest.php" => $this->integrationTestTemplate($resource),
            ROOTPATH . "tests/Feature/Controllers/{$domain}/{$resource}ControllerTest.php" => $this->featureTestTemplate($resource, $route),
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

        $serviceRegistered = $this->registerServiceMethod($resource, $resourceLower);
        if ($serviceRegistered) {
            CLI::write('Updated: app/Config/Services.php (service registration)', 'green');
        } else {
            CLI::write('Skipped service registration: method already exists or insertion point not found.', 'yellow');
        }

        CLI::newLine();
        CLI::write('Route snippet (add to app/Config/Routes.php):', 'cyan');
        if ($publicRead) {
            CLI::write("\$routes->get('{$route}', 'App\\Controllers\\Api\\V1\\{$domain}\\{$resource}Controller::index');");
            CLI::write("\$routes->get('{$route}/(:num)', 'App\\Controllers\\Api\\V1\\{$domain}\\{$resource}Controller::show/$1');");
        }
        if ($adminWrite) {
            CLI::write("\$routes->post('{$route}', 'App\\Controllers\\Api\\V1\\{$domain}\\{$resource}Controller::create');");
            CLI::write("\$routes->put('{$route}/(:num)', 'App\\Controllers\\Api\\V1\\{$domain}\\{$resource}Controller::update/$1');");
            CLI::write("\$routes->delete('{$route}/(:num)', 'App\\Controllers\\Api\\V1\\{$domain}\\{$resource}Controller::delete/$1');");
        }

        CLI::newLine();
        CLI::write('Next steps:', 'cyan');
        CLI::write("1) Add {$resource}Validation registration in InputValidationService.");
        CLI::write('2) Create migration for table schema and indexes.');
        CLI::write('3) Implement service business rules and complete tests.');

        return EXIT_SUCCESS;
    }

    private function registerServiceMethod(string $resource, string $resourceLower): bool
    {
        $servicesPath = APPPATH . 'Config/Services.php';
        $content = (string) file_get_contents($servicesPath);
        $methodName = "{$resourceLower}Service";

        if (str_contains($content, "function {$methodName}(")) {
            return false;
        }

        $method = "\n    /**\n     * {$resource} Service\n     */\n    public static function {$methodName}(bool \$getShared = true)\n    {\n        if (\$getShared) {\n            return static::getSharedInstance('{$methodName}');\n        }\n\n        return new \\App\\Services\\{$resource}Service(\n            new \\App\\Models\\{$resource}Model()\n        );\n    }\n";

        $needle = "\n}\n";
        $position = strrpos($content, $needle);

        if ($position === false) {
            return false;
        }

        $updated = substr($content, 0, $position) . $method . substr($content, $position);
        file_put_contents($servicesPath, $updated);

        return true;
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
use CodeIgniter\Model;

class {$resource}Model extends Model
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

    private function interfaceTemplate(string $resource): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Interfaces;

interface {$resource}ServiceInterface extends CrudServiceContract
{
}
PHP;
    }

    private function serviceTemplate(string $resource, string $resourceLower): string
    {
        $langDomain = $this->pluralize($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\{$resource}ServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\{$resource}Model;
use App\Traits\AppliesQueryOptions;

class {$resource}Service extends BaseCrudService implements {$resource}ServiceInterface
{
    use AppliesQueryOptions;

    public function __construct(
        protected {$resource}Model \${$resourceLower}Model
    ) {
    }

    public function index(array \$data): array
    {
        \$builder = new QueryBuilder(\$this->{$resourceLower}Model);
        \$this->applyQueryOptions(\$builder, \$data);

        [\$page, \$limit] = \$this->resolvePagination(\$data, (int) env('PAGINATION_DEFAULT_LIMIT', 20));
        \$result = \$builder->paginate(\$page, \$limit);
        \$result['data'] = array_map(static fn (\$item) => \$item->toArray(), \$result['data']);

        return ApiResponse::paginated(\$result['data'], \$result['total'], \$result['page'], \$result['perPage']);
    }

    public function show(array \$data): array
    {
        \$id = \$this->requireId(\$data);
        \$item = \$this->{$resourceLower}Model->find(\$id);

        if (!\$item) {
            throw new NotFoundException(lang('{$langDomain}.notFound'));
        }

        return ApiResponse::success(\$item->toArray());
    }

    public function store(array \$data): array
    {
        validateOrFail(\$data, '{$resourceLower}', 'store');

        \$id = \$this->{$resourceLower}Model->insert(\$data);
        if (!\$id) {
            throw new ValidationException(lang('Api.validationFailed'), \$this->{$resourceLower}Model->errors());
        }

        \$item = \$this->{$resourceLower}Model->find(\$id);

        return ApiResponse::created(\$item->toArray());
    }

    public function update(array \$data): array
    {
        \$id = \$this->requireId(\$data);
        validateOrFail(\$data, '{$resourceLower}', 'update');

        \$success = \$this->{$resourceLower}Model->update(\$id, \$data);
        if (!\$success) {
            throw new ValidationException(lang('Api.validationFailed'), \$this->{$resourceLower}Model->errors());
        }

        \$item = \$this->{$resourceLower}Model->find(\$id);

        return ApiResponse::success(\$item->toArray());
    }

    public function destroy(array \$data): array
    {
        \$id = \$this->requireId(\$data);

        if (!\$this->{$resourceLower}Model->find(\$id)) {
            throw new NotFoundException(lang('{$langDomain}.notFound'));
        }

        \$this->{$resourceLower}Model->delete(\$id);

        return ApiResponse::deleted(lang('{$langDomain}.deletedSuccess'));
    }
}
PHP;
    }

    private function validationTemplate(string $resource): string
    {
        $domain = lcfirst($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Validations;

class {$resource}Validation extends BaseValidation
{
    public function getRules(string \$action): array
    {
        return match (\$action) {
            'index' => \$this->paginationRules(),
            'show', 'destroy' => \$this->idRules(),
            'store' => [
                'name' => 'required|string|max_length[255]',
            ],
            'update' => \$this->mergeRules(
                \$this->idRules(),
                [
                    'name' => 'permit_empty|string|max_length[255]',
                ]
            ),
            default => [],
        };
    }

    public function getMessages(string \$action): array
    {
        return match (\$action) {
            'index' => \$this->paginationMessages(),
            'show', 'destroy' => \$this->idMessages(),
            'store', 'update' => [
                'name.required' => lang('InputValidation.{$domain}.nameRequired'),
                'name.max_length' => lang('InputValidation.{$domain}.nameMaxLength'),
            ],
            default => [],
        };
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

class {$resource}Controller extends ApiController
{
    protected string \$serviceName = '{$resourceLower}Service';
}
PHP;
    }

    private function docSchemaTemplate(string $resource): string
    {
        $plural = $this->pluralize($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Documentation\\{$plural};

/**
 * @OA\Schema(
 *   schema="{$resource}",
 *   type="object",
 *   @OA\Property(property="id", type="integer"),
 *   @OA\Property(property="name", type="string")
 * )
 */
class {$resource}Schema
{
}
PHP;
    }

    private function docEndpointsTemplate(string $resource, string $route): string
    {
        $plural = $this->pluralize($resource);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Documentation\\{$plural};

/**
 * OpenAPI placeholders for {$resource} endpoints.
 *
 * @OA\Tag(name="{$plural}", description="{$resource} management")
 */
class {$resource}Endpoints
{
}
PHP;
    }

    private function langTemplate(string $resource, bool $spanish): string
    {
        $singular = $spanish ? strtolower($resource) : strtolower($resource);
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

    private function unitTestTemplate(string $resource): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;

class {$resource}ServiceTest extends CIUnitTestCase
{
    public function testPlaceholder(): void
    {
        \$this->markTestIncomplete('Implement {$resource}Service unit tests.');
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
    protected \$migrateOnce = false;
    protected \$refresh = true;
    protected \$namespace = 'App';

    public function testPlaceholder(): void
    {
        \$this->markTestIncomplete('Implement {$resource}Model integration tests.');
    }
}
PHP;
    }

    private function featureTestTemplate(string $resource, string $route): string
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
    protected \$migrateOnce = false;
    protected \$refresh = true;
    protected \$namespace = 'App';

    public function testPlaceholder(): void
    {
        \$this->markTestIncomplete('Implement {$resource} feature tests for /api/v1/{$route}.');
    }
}
PHP;
    }
}
