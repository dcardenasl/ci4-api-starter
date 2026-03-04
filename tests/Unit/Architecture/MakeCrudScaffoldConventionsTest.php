<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrails for generated CRUD scaffold defaults.
 */
class MakeCrudScaffoldConventionsTest extends CIUnitTestCase
{
    public function testMakeCrudModelTemplateInitializesAuditableTrait(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Commands/MakeCrud.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('class {$resource}Model extends BaseAuditableModel', $source);
        $this->assertStringNotContainsString('use App\Traits\Auditable;', $source);
    }

    public function testMakeCrudRouteSnippetUsesStandardApiFilters(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Commands/MakeCrud.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString("['filter' => ['jwtauth', 'throttle']]", $source);
        $this->assertStringContainsString("['filter' => ['jwtauth', 'roleauth:admin', 'throttle']]", $source);
    }

    public function testMakeCrudDtoTemplatesUsePublicRulesMethod(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Commands/MakeCrud.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('public function rules(): array', $source);
        $this->assertStringNotContainsString('protected function rules(): array', $source);
    }
}
