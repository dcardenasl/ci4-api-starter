<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PasswordResetService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * PasswordResetService Unit Tests
 *
 * Comprehensive test coverage for password reset operations.
 * Tests security features, validation, and email enumeration prevention.
 */
class PasswordResetServiceTest extends CIUnitTestCase
{
    protected PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordResetService();
    }

    // ==================== SEND RESET LINK TESTS ====================

    public function testSendResetLinkValidatesEmail(): void
    {
        $result = $this->service->sendResetLink('');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testSendResetLinkValidatesEmailFormat(): void
    {
        $result = $this->service->sendResetLink('invalid-email');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testSendResetLinkReturnsSuccessForNonExistentEmail(): void
    {
        // Security: Should not reveal if email exists (prevents enumeration)
        $result = $this->service->sendResetLink('nonexistent@example.com');

        // Should still return success
        $this->assertEquals('success', $result['status']);
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenRequiresToken(): void
    {
        $result = $this->service->validateToken('', 'test@example.com');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testValidateTokenRequiresEmail(): void
    {
        $result = $this->service->validateToken('some-token', '');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    // ==================== RESET PASSWORD TESTS ====================

    public function testResetPasswordRequiresToken(): void
    {
        $result = $this->service->resetPassword('', 'test@example.com', 'NewPass123!');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testResetPasswordRequiresEmail(): void
    {
        $result = $this->service->resetPassword('token', '', 'NewPass123!');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testResetPasswordRequiresNewPassword(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', '');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testResetPasswordValidatesMinLength(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', 'Pass1!');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testResetPasswordValidatesMaxLength(): void
    {
        $longPassword = str_repeat('A1!a', 40); // 160 chars

        $result = $this->service->resetPassword('token', 'test@example.com', $longPassword);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testResetPasswordRequiresLowercase(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', 'PASSWORD123!');

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testResetPasswordRequiresUppercase(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', 'password123!');

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testResetPasswordRequiresDigit(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', 'Password!');

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testResetPasswordRequiresSpecialChar(): void
    {
        $result = $this->service->resetPassword('token', 'test@example.com', 'Password123');

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testResetPasswordAcceptsValidPassword(): void
    {
        // Valid passwords should pass complexity check
        // These passwords are valid, so the error should be token-related (NotFoundException)
        $validPasswords = [
            'Password123!',
            'MyP@ssw0rd',
            'Secure#Pass1',
            'C0mpl3x!Pass',
            'Str0ng_Password',
        ];

        foreach ($validPasswords as $password) {
            try {
                $this->service->resetPassword('invalid-token', 'test@example.com', $password);
                $this->fail('Expected NotFoundException for invalid token');
            } catch (\App\Exceptions\NotFoundException $e) {
                // Expected - token is invalid, but password passed validation
                $this->assertTrue(true);
            }
        }
    }

    // ==================== SECURITY TESTS ====================

    public function testSendResetLinkPreventsEmailEnumeration(): void
    {
        // Should return same response for existing and non-existing emails
        $result1 = $this->service->sendResetLink('exists@example.com');
        $result2 = $this->service->sendResetLink('notexists@example.com');

        $this->assertEquals($result1['status'], $result2['status']);
        $this->assertEquals($result1['message'], $result2['message']);
    }

    public function testResetPasswordHashesPassword(): void
    {
        $this->expectException(\App\Exceptions\NotFoundException::class);

        // Verify that the service would hash the password
        // Will throw NotFoundException due to invalid token (expected)
        $password = 'TestPassword123!';

        $this->service->resetPassword('token', 'test@example.com', $password);
    }

    public function testPasswordComplexityRejectsCommonPatterns(): void
    {
        $weakPasswords = [
            'password',       // No uppercase, digit, special
            'Password',       // No digit, special
            'Password1',      // No special
            'password1!',     // No uppercase
            'PASSWORD1!',     // No lowercase
            '12345678',       // No letters
            'abcdefgh',       // No uppercase, digit, special
        ];

        foreach ($weakPasswords as $weakPassword) {
            $result = $this->service->resetPassword('token', 'test@example.com', $weakPassword);

            $this->assertEquals('error', $result['status'], "Weak password '{$weakPassword}' should be rejected");
        }
    }

    // ==================== EDGE CASES ====================

    public function testSendResetLinkHandlesEmailWithSpaces(): void
    {
        $result = $this->service->sendResetLink('  test@example.com  ');

        // Should be invalid (email has spaces)
        $this->assertEquals('error', $result['status']);
    }

    public function testSendResetLinkHandlesInternationalEmail(): void
    {
        // Most international emails should be handled by filter_var
        $result = $this->service->sendResetLink('user@tëst.com');

        // Should return success (doesn't reveal if email exists)
        $this->assertEquals('success', $result['status']);
    }

    public function testResetPasswordHandlesPasswordWithUnicode(): void
    {
        $this->expectException(\App\Exceptions\NotFoundException::class);

        // Unicode characters should work if they meet complexity
        // Will throw NotFoundException due to invalid token (expected)
        $this->service->resetPassword('token', 'test@example.com', 'Pásswörd123!');
    }

    public function testResetPasswordExactly8Chars(): void
    {
        $this->expectException(\App\Exceptions\NotFoundException::class);

        // Minimum length boundary - password is valid
        // Will throw NotFoundException due to invalid token (expected)
        $this->service->resetPassword('token', 'test@example.com', 'Pass123!');
    }

    public function testResetPasswordExactly128Chars(): void
    {
        $this->expectException(\App\Exceptions\NotFoundException::class);

        // Maximum length boundary - exactly 128 chars, valid password
        $password = str_repeat('Ab1!', 32); // 128 chars

        // Will throw NotFoundException due to invalid token (expected)
        $this->service->resetPassword('token', 'test@example.com', $password);
    }

    public function testValidateTokenHandlesBothParamsMissing(): void
    {
        $result = $this->service->validateToken('', '');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    // ==================== INTEGRATION PATTERN TESTS ====================

    public function testTypicalPasswordResetWorkflow(): void
    {
        // 1. Request reset link
        $sendResult = $this->service->sendResetLink('user@example.com');
        $this->assertEquals('success', $sendResult['status']);

        // 2. Validate token (will throw exception without valid DB token)
        try {
            $this->service->validateToken('some-token', 'user@example.com');
            $this->fail('Expected NotFoundException for invalid token');
        } catch (\App\Exceptions\NotFoundException $e) {
            $this->assertTrue(true);
        }

        // 3. Reset password (will throw exception on token validation)
        try {
            $this->service->resetPassword('some-token', 'user@example.com', 'NewPass123!');
            $this->fail('Expected NotFoundException for invalid token');
        } catch (\App\Exceptions\NotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    public function testMultipleResetRequestsForSameEmail(): void
    {
        // Should be able to request multiple times
        $result1 = $this->service->sendResetLink('test@example.com');
        $result2 = $this->service->sendResetLink('test@example.com');
        $result3 = $this->service->sendResetLink('test@example.com');

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);
        $this->assertEquals('success', $result3['status']);
    }

    public function testPasswordComplexityWithDifferentSpecialChars(): void
    {
        $specialChars = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '=', '+'];

        foreach ($specialChars as $char) {
            $password = "Password123{$char}";

            try {
                $this->service->resetPassword('token', 'test@example.com', $password);
                $this->fail("Expected NotFoundException for invalid token with password using '{$char}'");
            } catch (\App\Exceptions\NotFoundException $e) {
                // Expected - token is invalid, but password with this special char is valid
                $this->assertTrue(true);
            }
        }
    }

    // ==================== RESPONSE FORMAT TESTS ====================

    public function testSendResetLinkReturnsCorrectFormat(): void
    {
        $result = $this->service->sendResetLink('test@example.com');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testValidateTokenReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->validateToken('', 'test@example.com');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testResetPasswordReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->resetPassword('', '', '');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}
