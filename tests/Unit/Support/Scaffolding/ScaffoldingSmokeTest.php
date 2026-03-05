<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Scaffolding;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\ScaffoldingOrchestrator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * ScaffoldingSmokeTest
 * Verifies that the modular generators produce valid PHP files.
 */
class ScaffoldingSmokeTest extends CIUnitTestCase
{
    private string $resource = 'SmokeTestResource';
    private string $domain = 'Testing';

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupGeneratedFiles();
    }

    public function testOrchestratorGeneratesValidPhpFiles(): void
    {
        $fields = [
            new Field(name: 'name', type: 'string', required: true, searchable: true, filterable: true),
            new Field(name: 'price', type: 'decimal', required: true, filterable: true),
            new Field(name: 'category_id', type: 'fk', required: true, filterable: true, fkTable: 'categories'),
        ];

        $schema = new ResourceSchema(
            resource: $this->resource,
            domain: $this->domain,
            route: 'smoke-test-resources',
            fields: $fields
        );

        $orchestrator = new ScaffoldingOrchestrator();
        $createdFiles = $orchestrator->orchestrate($schema);

        $this->assertNotEmpty($createdFiles, 'No files were generated.');

        foreach ($createdFiles as $path) {
            $this->assertFileExists($path);

            // Syntax check (Lint) the generated file
            $output = [];
            $returnValue = 0;
            exec("php -l " . escapeshellarg($path), $output, $returnValue);

            $this->assertSame(0, $returnValue, "Syntax error in generated file: {$path}\n" . implode("\n", $output));
        }
    }

    private function cleanupGeneratedFiles(): void
    {
        $files = [
            APPPATH . "Entities/{$this->resource}Entity.php",
            APPPATH . "Models/{$this->resource}Model.php",
            APPPATH . "Interfaces/{$this->domain}/{$this->resource}ServiceInterface.php",
            APPPATH . "Services/{$this->domain}/{$this->resource}Service.php",
            APPPATH . "Controllers/Api/V1/{$this->domain}/{$this->resource}Controller.php",
            APPPATH . "Documentation/{$this->domain}/{$this->resource}Endpoints.php",
            APPPATH . "DTO/Request/{$this->domain}/{$this->resource}IndexRequestDTO.php",
            APPPATH . "DTO/Request/{$this->domain}/{$this->resource}CreateRequestDTO.php",
            APPPATH . "DTO/Request/{$this->domain}/{$this->resource}UpdateRequestDTO.php",
            APPPATH . "DTO/Response/{$this->domain}/{$this->resource}ResponseDTO.php",
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // Remove domain directories if empty
        $dirs = [
            APPPATH . "Interfaces/{$this->domain}",
            APPPATH . "Services/{$this->domain}",
            APPPATH . "Controllers/Api/V1/{$this->domain}",
            APPPATH . "Documentation/{$this->domain}",
            APPPATH . "DTO/Request/{$this->domain}",
            APPPATH . "DTO/Response/{$this->domain}",
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                @rmdir($dir);
            }
        }
    }
}
