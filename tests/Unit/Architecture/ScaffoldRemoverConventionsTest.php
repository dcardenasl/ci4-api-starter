<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\ScaffoldRemover;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The ScaffoldRemover must produce the inverse of the orchestrator: every file
 * the engine creates must appear in the planned removal set. This catches the
 * audit P1 follow-on: when a generator is added without updating the remover,
 * leftover artifacts accumulate after make:crud:remove.
 */
class ScaffoldRemoverConventionsTest extends CIUnitTestCase
{
    public function testPlanIncludesAllFixedFilePaths(): void
    {
        $schema = new ResourceSchema(
            resource: 'TestThing',
            domain: 'TestDomain',
            route: 'test-things',
            fields: [],
        );

        $report = (new ScaffoldRemover())->plan($schema);

        // The remover lists "would delete" paths only when they exist on disk.
        // Since we're running on a clean test instance the list will be empty,
        // but the keys of the report must always be present and well-typed.
        $this->assertArrayHasKey('deleted', $report);
        $this->assertArrayHasKey('not_found', $report);
        $this->assertArrayHasKey('routes_cleaned', $report);
        $this->assertArrayHasKey('trait_cleaned', $report);
        $this->assertArrayHasKey('trait_removed', $report);
        $this->assertArrayHasKey('services_cleaned', $report);
        $this->assertArrayHasKey('migration', $report);
    }

    public function testPlanCoversCanonicalGeneratedPaths(): void
    {
        $schema = new ResourceSchema(
            resource: 'Sample',
            domain: 'TestDomain',
            route: 'samples',
            fields: [],
        );

        // The "not_found" array contains paths checked but absent on disk.
        // It encodes the remover's contract — if any of these paths are missing
        // from the list, a generator was added without updating the remover.
        $report = (new ScaffoldRemover())->plan($schema);
        $allChecked = array_merge($report['deleted'], $report['not_found']);
        $contract = [
            'Controllers/Api/V1/TestDomain/SampleController.php',
            'Services/TestDomain/SampleService.php',
            'Interfaces/TestDomain/SampleServiceInterface.php',
            'DTO/Request/TestDomain/SampleIndexRequestDTO.php',
            'DTO/Request/TestDomain/SampleCreateRequestDTO.php',
            'DTO/Request/TestDomain/SampleUpdateRequestDTO.php',
            'DTO/Response/TestDomain/SampleResponseDTO.php',
            'Documentation/TestDomain/SampleEndpoints.php',
            'Models/SampleModel.php',
            'Entities/SampleEntity.php',
            'Language/en/Samples.php',
            'Language/es/Samples.php',
        ];
        foreach ($contract as $needle) {
            $found = false;
            foreach ($allChecked as $path) {
                if (str_contains($path, $needle)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Remover does not cover '{$needle}' — a generator was likely added without updating ScaffoldRemover.");
        }
    }
}
