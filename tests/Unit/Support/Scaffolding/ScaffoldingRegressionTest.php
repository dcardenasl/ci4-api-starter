<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Scaffolding;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\Generators\DtoGenerator;
use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\ScaffoldingOrchestrator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression tests for scaffolding engine bugs.
 */
class ScaffoldingRegressionTest extends CIUnitTestCase
{
    /**
     * @var string[]
     */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupGeneratedFiles();
    }

    public function testOrchestratorAllowsRouteUpsertWithinSameDomain(): void
    {
        $fields = [
            new Field(name: 'name', type: 'string', required: true),
        ];

        $schemaA = new ResourceSchema(
            resource: 'AlphaWidget',
            domain: 'RouteTest',
            route: 'alpha-widgets',
            fields: $fields
        );

        $schemaB = new ResourceSchema(
            resource: 'BetaWidget',
            domain: 'RouteTest',
            route: 'beta-widgets',
            fields: $fields
        );

        $orchestrator = new ScaffoldingOrchestrator();
        $this->createdFiles = array_merge(
            $this->createdFiles,
            $orchestrator->orchestrate($schemaA)
        );

        // Ensure migration filenames don't collide when called quickly.
        usleep(1100000);

        $this->createdFiles = array_merge(
            $this->createdFiles,
            $orchestrator->orchestrate($schemaB)
        );

        $routeFile = APPPATH . 'Config/Routes/v1/route-test.php';
        $this->assertFileExists($routeFile);
        $content = (string) file_get_contents($routeFile);

        $this->assertStringContainsString('AlphaWidgetController::index', $content);
        $this->assertStringContainsString('BetaWidgetController::index', $content);
    }

    public function testRequestDtosIncludeSchemaAttribute(): void
    {
        $schema = new ResourceSchema(
            resource: 'DtoSchemaProbe',
            domain: 'Docs',
            route: 'dto-schema-probes',
            fields: [
                new Field(name: 'name', type: 'string', required: true),
            ]
        );

        $generator = new DtoGenerator();
        $files = $generator->generate($schema);

        $indexContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeIndexRequestDTO.php'];
        $createContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeCreateRequestDTO.php'];
        $updateContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeUpdateRequestDTO.php'];

        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeIndexRequest')]", $indexContent);
        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeCreateRequest')]", $createContent);
        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeUpdateRequest')]", $updateContent);
    }

    private function cleanupGeneratedFiles(): void
    {
        $files = array_unique($this->createdFiles);
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->cleanupEmptyDirectories($files);
        $this->createdFiles = [];
    }

    /**
     * @param string[] $files
     */
    private function cleanupEmptyDirectories(array $files): void
    {
        $dirs = [];
        foreach ($files as $file) {
            $dirs[] = dirname($file);
        }

        $dirs = array_unique($dirs);
        usort($dirs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($dirs as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                @rmdir($dir);
            }
        }
    }
}
