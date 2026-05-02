<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\Scaffolding\ScaffoldConflictException;
use App\Support\Scaffolding\ScaffoldingOrchestrator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Audit P0 regression: ensure ScaffoldingOrchestrator detects case-insensitive
 * collisions explicitly (separate from exact-match conflicts) so the message
 * surfaces the macOS/Linux divergence to the user rather than misleading them.
 */
class ScaffoldOrchestratorCollisionsTest extends CIUnitTestCase
{
    public function testExceptionSurfacesCaseInsensitiveCollisionsSeparately(): void
    {
        $exact = ['/some/path/Foo.php'];
        $caseInsensitive = ['/some/path/APIKey.php' => '/some/path/ApiKey.php'];

        $exception = new ScaffoldConflictException($exact, $caseInsensitive);
        $message = $exception->getMessage();

        $this->assertStringContainsString('case-insensitive', $message);
        $this->assertStringContainsString('APIKey.php', $message);
        $this->assertStringContainsString('ApiKey.php', $message);
        $this->assertStringContainsString("'ApiKey' instead of 'APIKey'", $message);
    }

    public function testOrchestratorExposesPlanForDryRun(): void
    {
        $reflection = new \ReflectionClass(ScaffoldingOrchestrator::class);
        $this->assertTrue($reflection->hasMethod('plan'), 'Orchestrator must expose plan() for --dry-run support');
        $this->assertTrue($reflection->hasMethod('wasExisting'), 'Orchestrator must expose wasExisting() so callers can label CREATED vs UPDATED');
    }

    public function testRollbackRestoresPreExistingFileInsteadOfDeleting(): void
    {
        $dir  = sys_get_temp_dir() . '/scaffold_rollback_test_' . uniqid('', true);
        mkdir($dir, 0775, true);

        $existingPath = $dir . '/existing.php';
        $originalContent = '<?php // original';
        file_put_contents($existingPath, $originalContent);

        $orchestrator = new ScaffoldingOrchestrator();
        $rollback = new \ReflectionMethod($orchestrator, 'rollback');
        $rollback->setAccessible(true);

        // Simulate: existing.php was overwritten, then a later write failed and rollback runs.
        file_put_contents($existingPath, '<?php // overwritten by scaffold');

        $rollback->invoke($orchestrator, [$existingPath], [$existingPath => $originalContent]);

        $this->assertFileExists($existingPath, 'Pre-existing file must not be deleted on rollback');
        $this->assertSame($originalContent, file_get_contents($existingPath), 'Pre-existing file must be restored to its original content');

        unlink($existingPath);
        rmdir($dir);
    }

    public function testRollbackDeletesNewFileWithNoSnapshot(): void
    {
        $dir  = sys_get_temp_dir() . '/scaffold_rollback_test_' . uniqid('', true);
        mkdir($dir, 0775, true);

        $newPath = $dir . '/new.php';
        file_put_contents($newPath, '<?php // new file');

        $orchestrator = new ScaffoldingOrchestrator();
        $rollback = new \ReflectionMethod($orchestrator, 'rollback');
        $rollback->setAccessible(true);

        $rollback->invoke($orchestrator, [$newPath], []);

        $this->assertFileDoesNotExist($newPath, 'New file must be deleted on rollback');

        rmdir($dir);
    }
}
