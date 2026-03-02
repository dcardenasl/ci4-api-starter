<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Services\Catalog\DemoproductService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

class DemoproductServiceTest extends CIUnitTestCase
{
    public function testIndexContractReturnsDataTransferObjectInterface(): void
    {
        $method = new ReflectionMethod(DemoproductService::class, 'index');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, $returnType?->getName());
    }

    public function testStoreAndUpdateSignaturesUseDtoAndSecurityContext(): void
    {
        $store = new ReflectionMethod(DemoproductService::class, 'store');
        $update = new ReflectionMethod(DemoproductService::class, 'update');

        $storeParams = $store->getParameters();
        $updateParams = $update->getParameters();
        $storeContextType = (string) $storeParams[1]->getType();
        $updateContextType = (string) $updateParams[2]->getType();

        $this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, (string) $storeParams[0]->getType());
        $this->assertSame(\App\DTO\SecurityContext::class, ltrim($storeContextType, '?'));
        $this->assertSame(\App\Interfaces\DataTransferObjectInterface::class, (string) $updateParams[1]->getType());
        $this->assertSame(\App\DTO\SecurityContext::class, ltrim($updateContextType, '?'));
    }
}
