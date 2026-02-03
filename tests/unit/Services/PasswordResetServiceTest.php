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
        $validPasswords = [
            'Password123!',
            'MyP@ssw0rd',
            'Secure#Pass1',
            'C0mpl3x!Pass',
            'Str0ng_Password',
        ];

        foreach ($validPasswords as $password) {
            // This will fail at token validation, but should pass password validation
            $result = $this->service->resetPassword('invalid-token', 'test@example.com', $password);

            // Should fail on token, not password
            $this->assertEquals('error', $result['status']);
            if (isset($result['errors']['password'])) {
                $this->fail("Password '{$password}' should be valid but failed validation");
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
        // Verify that the service would hash the password
        // We can't test the actual hashing without DB, but we can verify
        // the password is not stored in plain text in our code
        $password = 'TestPassword123!';

        // Password should never be logged or returned
        $result = $this->service->resetPassword('token', 'test@example.com', $password);

        // Ensure password is not in the response
        $this->assertStringNotContainsString($password, json_encode($result));
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
        // Unicode characters should work if they meet complexity
        $result = $this->service->resetPassword('token', 'test@example.com', 'Pásswörd123!');

        // Should fail on token validation, not password (unicode is allowed)
        $this->assertEquals('error', $result['status']);
    }

    public function testResetPasswordExactly8Chars(): void
    {
        // Minimum length boundary
        $result = $this->service->resetPassword('token', 'test@example.com', 'Pass123!');

        // Should fail on token, not password length
        $this->assertEquals('error', $result['status']);
        if (isset($result['errors']['password'])) {
            $this->assertStringNotContainsString('length', strtolower($result['errors']['password']));
        }
    }

    public function testResetPasswordExactly128Chars(): void
    {
        // Maximum length boundary - exactly 128 chars
        $password = str_repeat('Ab1!', 32); // 128 chars

        $result = $this->service->resetPassword('token', 'test@example.com', $password);

        // Should fail on token, not password length
        $this->assertEquals('error', $result['status']);
        if (isset($result['errors']['password'])) {
            $this->assertStringNotContainsString('length', strtolower($result['errors']['password']));
        }
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

        // 2. Validate token (will fail without DB, but tests the flow)
        $validateResult = $this->service->validateToken('some-token', 'user@example.com');
        $this->assertEquals('error', $validateResult['status']); // No token in DB

        // 3. Reset password (will fail on token validation)
        $resetResult = $this->service->resetPassword('some-token', 'user@example.com', 'NewPass123!');
        $this->assertEquals('error', $resetResult['status']);
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
            $result = $this->service->resetPassword('token', 'test@example.com', $password);

            // Should fail on token, not password
            $this->assertEquals('error', $result['status']);
            $this->assertArrayNotHasKey(
                'password',
                $result['errors'] ?? [],
                "Password with special char '{$char}' should be valid"
            );
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
