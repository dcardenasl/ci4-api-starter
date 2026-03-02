<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class BaseRequestDtoConventionsTest extends CIUnitTestCase
{
    public function testBaseRequestDtoStaysPureAndFrameworkAgnostic(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/DTO/Request/BaseRequestDTO.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);

        $this->assertStringNotContainsString('enrichWithContext', $source);
        $this->assertStringNotContainsString('ContextHolder', $source);
        $this->assertStringNotContainsString('Services::request', $source);
        $this->assertStringNotContainsString('?SecurityContext', $source);
    }
}
