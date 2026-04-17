<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\StringHelper;

/**
 * TestGenerator
 * Emits Unit/Integration/Feature test stubs for the new resource.
 *
 * Each stub includes at least one assertion that exercises the scaffolded code,
 * so the generated suite passes `vendor/bin/phpunit` immediately. Developers
 * extend these instead of deleting markTestIncomplete() calls.
 */
class TestGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;

        return [
            ROOTPATH . "tests/Unit/Services/{$domain}/{$resource}ServiceTest.php" => $this->unitTestTemplate($schema),
            ROOTPATH . "tests/Integration/Models/{$resource}ModelTest.php" => $this->integrationTestTemplate($schema),
            ROOTPATH . "tests/Feature/Controllers/{$domain}/{$resource}ControllerTest.php" => $this->featureTestTemplate($schema),
        ];
    }

    private function unitTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\\{$schema->domain};

use App\Interfaces\\{$schema->domain}\\{$resource}ServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Smoke tests for {$resource}Service. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class {$resource}ServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        \$service = Services::{$resourceLower}Service(false);

        \$this->assertInstanceOf({$resource}ServiceInterface::class, \$service);
    }
}
PHP;
    }

    private function integrationTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\\{$resource}Model;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke tests for {$resource}Model. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class {$resource}ModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected \$migrate     = true;
    protected \$migrateOnce = true;
    protected \$refresh     = true;
    protected \$namespace   = 'App';

    public function testModelReportsCorrectTable(): void
    {
        \$model = new {$resource}Model();

        \$this->assertSame('{$schema->getResourcePluralSnakeCase()}', \$model->getTable());
    }
}
PHP;
    }

    private function featureTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        // Routes are nested under the kebab-cased domain: /api/v1/{domain-kebab}/{route}.
        // See RouteGenerator::baseTemplate().
        $fullPath = '/api/v1/' . StringHelper::toKebab($schema->domain) . '/' . $schema->route;

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\\{$schema->domain};

use Tests\Support\ApiTestCase;

/**
 * HTTP smoke tests for {$resource}Controller. The default route group wraps
 * every endpoint in the jwtauth filter, so an unauthenticated request must
 * return 401 — a sufficient signal that the route was registered and wired.
 *
 * Extend with authenticated 200 flows (via AuthTestTrait) as business rules
 * solidify.
 *
 * @internal
 */
final class {$resource}ControllerTest extends ApiTestCase
{
    public function testIndexRequiresAuthentication(): void
    {
        \$this->clearTestRequestHeaders();
        \$result = \$this->get('{$fullPath}');

        \$result->assertStatus(401);
    }
}
PHP;
    }
}
