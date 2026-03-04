<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Interfaces\Files\FileServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class ServicesWiringTest extends CIUnitTestCase
{
    public function testFileResponseMapperFactoryReturnsResponseMapper(): void
    {
        $mapper = Services::fileResponseMapper(false);

        $this->assertInstanceOf(ResponseMapperInterface::class, $mapper);
    }

    public function testFileServiceFactoryResolvesDependencies(): void
    {
        $service = Services::fileService(false);

        $this->assertInstanceOf(FileServiceInterface::class, $service);
    }
}
