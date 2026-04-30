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
}
