<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * Guardrail for command-style service operations that must return OperationResult.
 */
class ServiceOperationResultContractsTest extends CIUnitTestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function contractMap(): array
    {
        return [
            \App\Services\AuthService::class => ['loginWithGoogleToken'],
            \App\Services\AuthTokenService::class => ['revokeToken', 'revokeAllUserTokens'],
            \App\Services\MetricsService::class => ['record'],
            \App\Services\RefreshTokenService::class => ['revoke', 'revokeAllUserTokens'],
        ];
    }

    public function testMappedServiceMethodsReturnOperationResult(): void
    {
        $violations = [];

        foreach ($this->contractMap() as $class => $methods) {
            foreach ($methods as $methodName) {
                $method = new ReflectionMethod($class, $methodName);
                $returnType = $method->getReturnType();
                $typeName = $returnType !== null ? $returnType->getName() : '';

                if ($typeName !== \App\Support\OperationResult::class) {
                    $violations[] = "{$class}::{$methodName} must return " . \App\Support\OperationResult::class;
                }
            }
        }

        $this->assertSame([], $violations, "OperationResult contract violations:\n- " . implode("\n- ", $violations));
    }
}
