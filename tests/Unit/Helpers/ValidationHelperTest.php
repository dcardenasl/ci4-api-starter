<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Exceptions\ValidationException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validation Helper Unit Tests
 *
 * Tests the validation helper functions.
 */
class ValidationHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('validation');
    }

    // ==================== validateInputs() ====================

    public function testValidateInputsReturnsEmptyArrayForValidData(): void
    {
        $data = ['email' => 'test@example.com'];
        $rules = ['email' => 'required|valid_email'];

        $errors = validateInputs($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testValidateInputsReturnsErrorsForInvalidData(): void
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => 'required|valid_email'];

        $errors = validateInputs($data, $rules);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateInputsAcceptsCustomMessages(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => 'required'];
        $messages = ['email.required' => 'Custom email error'];

        $errors = validateInputs($data, $rules, $messages);

        $this->assertEquals('Custom email error', $errors['email']);
    }

    // ==================== validateOrFail() ====================

    public function testValidateOrFailDoesNothingForValidData(): void
    {
        $data = ['username' => 'test', 'password' => 'secret'];

        // Should not throw
        validateOrFail($data, 'auth', 'login');

        $this->assertTrue(true);
    }

    public function testValidateOrFailThrowsExceptionForInvalidData(): void
    {
        $data = ['username' => '', 'password' => ''];

        $this->expectException(ValidationException::class);

        validateOrFail($data, 'auth', 'login');
    }

    // ==================== getValidationRules() ====================

    public function testGetValidationRulesReturnsBothRulesAndMessages(): void
    {
        $result = getValidationRules('auth', 'login');

        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertNotEmpty($result['rules']);
    }

    public function testGetValidationRulesReturnsEmptyForUnknownDomain(): void
    {
        $result = getValidationRules('nonexistent', 'action');

        $this->assertEmpty($result['rules']);
        $this->assertEmpty($result['messages']);
    }

    // ==================== inputValidationService() ====================

    public function testInputValidationServiceReturnsServiceInstance(): void
    {
        $service = inputValidationService();

        $this->assertInstanceOf(\App\Interfaces\InputValidationServiceInterface::class, $service);
    }
}
