<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;

/**
 * TestGenerator
 * Generates Unit, Integration, and Feature tests for the new resource.
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
        return <<<PHP
<?php

namespace Tests\Unit\Services\\{$schema->domain};

use App\Services\\{$schema->domain}\\{$schema->resource}Service;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class {$schema->resource}ServiceTest extends TestCase
{
    public function testServiceIsInitializable(): void
    {
        \$this->markTestIncomplete('Implement unit tests for {$schema->resource}Service');
    }
}
PHP;
    }

    private function integrationTestTemplate(ResourceSchema $schema): string
    {
        return <<<PHP
<?php

namespace Tests\Integration\Models;

use App\Models\\{$schema->resource}Model;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class {$schema->resource}ModelTest extends DatabaseTestCase
{
    public function testModelCanFindRecord(): void
    {
        \$this->markTestIncomplete('Implement integration tests for {$schema->resource}Model');
    }
}
PHP;
    }

    private function featureTestTemplate(ResourceSchema $schema): string
    {
        return <<<PHP
<?php

namespace Tests\Feature\Controllers\\{$schema->domain};

use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class {$schema->resource}ControllerTest extends ApiTestCase
{
    public function testIndexReturnsSuccess(): void
    {
        \$this->markTestIncomplete('Implement feature tests for {$schema->resource}Controller');
    }
}
PHP;
    }
}
