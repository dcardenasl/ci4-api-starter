<?php

declare(strict_types=1);

namespace Tests\Unit\Validations;

use App\Validations\Rules\CustomRules;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * CustomRules Unit Tests
 *
 * Tests the custom validation rules.
 */
class CustomRulesTest extends CIUnitTestCase
{
    protected CustomRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new CustomRules();
    }

    // ==================== strong_password ====================

    public function testStrongPasswordValidWithAllRequirements(): void
    {
        $this->assertTrue($this->rules->strong_password('Password1!'));
        $this->assertTrue($this->rules->strong_password('SecureP@ss123'));
        $this->assertTrue($this->rules->strong_password('MyP@ssw0rd'));
    }

    public function testStrongPasswordFailsWithNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password(null, $error));
    }

    public function testStrongPasswordFailsWithEmptyString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('', $error));
    }

    public function testStrongPasswordFailsWithShortPassword(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Pass1!', $error));
        $this->assertStringContainsString('8', $error);
    }

    public function testStrongPasswordFailsWithLongPassword(): void
    {
        $error = null;
        $longPassword = str_repeat('a', 129);
        $this->assertFalse($this->rules->strong_password($longPassword, $error));
        $this->assertStringContainsString('128', $error);
    }

    public function testStrongPasswordFailsWithoutLowercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('PASSWORD1!', $error));
        $this->assertStringContainsString('lowercase', $error);
    }

    public function testStrongPasswordFailsWithoutUppercase(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('password1!', $error));
        $this->assertStringContainsString('uppercase', $error);
    }

    public function testStrongPasswordFailsWithoutDigit(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Password!', $error));
        $this->assertStringContainsString('digit', $error);
    }

    public function testStrongPasswordFailsWithoutSpecialChar(): void
    {
        $error = null;
        $this->assertFalse($this->rules->strong_password('Password1', $error));
        $this->assertStringContainsString('special', $error);
    }

    public function testStrongPasswordAcceptsUnderscoreAsSpecial(): void
    {
        $this->assertTrue($this->rules->strong_password('Password1_'));
    }

    // ==================== valid_email_idn ====================

    public function testValidEmailIdnWithStandardEmail(): void
    {
        $this->assertTrue($this->rules->valid_email_idn('test@example.com'));
        $this->assertTrue($this->rules->valid_email_idn('user.name@domain.org'));
        $this->assertTrue($this->rules->valid_email_idn('user+tag@example.co.uk'));
    }

    public function testValidEmailIdnWithInternationalDomain(): void
    {
        // International domain names (IDN)
        $this->assertTrue($this->rules->valid_email_idn('test@münchen.de'));
        $this->assertTrue($this->rules->valid_email_idn('user@日本.jp'));
    }

    public function testValidEmailIdnFailsWithNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn(null, $error));
    }

    public function testValidEmailIdnFailsWithEmptyString(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn('', $error));
    }

    public function testValidEmailIdnFailsWithInvalidFormat(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_email_idn('notanemail', $error));
        $this->assertFalse($this->rules->valid_email_idn('missing@domain', $error));
        $this->assertFalse($this->rules->valid_email_idn('@nodomain.com', $error));
    }

    // ==================== valid_uuid ====================

    public function testValidUuidWithValidV4Uuid(): void
    {
        $this->assertTrue($this->rules->valid_uuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue($this->rules->valid_uuid('6ba7b810-9dad-41d4-80b4-00c04fd430c8'));
    }

    public function testValidUuidFailsWithNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_uuid(null, $error));
    }

    public function testValidUuidFailsWithInvalidFormat(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_uuid('not-a-uuid', $error));
        $this->assertFalse($this->rules->valid_uuid('550e8400-e29b-51d4-a716-446655440000', $error)); // Version 5
        $this->assertFalse($this->rules->valid_uuid('550e8400e29b41d4a716446655440000', $error)); // No dashes
    }

    // ==================== valid_token ====================

    public function testValidTokenWithValidHexString(): void
    {
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $this->assertTrue($this->rules->valid_token($token));
    }

    public function testValidTokenWithCustomLength(): void
    {
        $token = bin2hex(random_bytes(16)); // 32 hex chars
        $this->assertTrue($this->rules->valid_token($token, '32'));
    }

    public function testValidTokenFailsWithNull(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_token(null, '64', [], $error));
    }

    public function testValidTokenFailsWithNonHexChars(): void
    {
        $error = null;
        $this->assertFalse($this->rules->valid_token('ghijklmnopqrstuvwxyz1234567890123456789012345678901234567890abcd', '64', [], $error));
        $this->assertStringContainsString('hexadecimal', $error);
    }

    public function testValidTokenFailsWithWrongLength(): void
    {
        $error = null;
        $token = bin2hex(random_bytes(16)); // 32 hex chars, expecting 64
        $this->assertFalse($this->rules->valid_token($token, '64', [], $error));
        $this->assertStringContainsString('64', $error);
    }
}
