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
    private string $resource = 'ScaffoldSmokeTest';
    private string $domain = 'ScaffoldTesting';
    private array $createdFiles = [];

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
            route: 'scaffold-smoke-tests',
            fields: $fields
        );

        $orchestrator = new ScaffoldingOrchestrator();
        $this->createdFiles = $orchestrator->orchestrate($schema);

        $this->assertNotEmpty($this->createdFiles, 'No files were generated.');

        foreach ($this->createdFiles as $path) {
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
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->cleanupEmptyDirectories();
        $this->createdFiles = [];
    }

    private function cleanupEmptyDirectories(): void
    {
        $dirs = [];
        foreach ($this->createdFiles as $file) {
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
