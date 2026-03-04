<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System;

use App\Services\System\AuditPayloadSanitizer;
use CodeIgniter\Test\CIUnitTestCase;

final class AuditPayloadSanitizerTest extends CIUnitTestCase
{
    public function testSanitizeRemovesSensitiveFieldsRecursively(): void
    {
        $sanitizer = new AuditPayloadSanitizer();

        $input = [
            'email' => 'user@example.com',
            'password' => 'secret',
            'profile' => [
                'token' => 'abc',
                'timezone' => 'UTC',
                'nested' => [
                    'refresh_token' => 'r1',
                    'enabled' => true,
                ],
            ],
        ];

        $result = $sanitizer->sanitize($input);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('token', $result['profile']);
        $this->assertArrayNotHasKey('refresh_token', $result['profile']['nested']);
        $this->assertSame('UTC', $result['profile']['timezone']);
        $this->assertTrue($result['profile']['nested']['enabled']);
    }
}
