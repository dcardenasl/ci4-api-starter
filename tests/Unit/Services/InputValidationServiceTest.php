<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ValidationException;
use App\Services\InputValidationService;
use App\Validations\BaseValidation;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * InputValidationService Unit Tests
 *
 * Tests the centralized validation service.
 */
class InputValidationServiceTest extends CIUnitTestCase
{
    protected InputValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InputValidationService();
    }

    // ==================== validate() ====================

    public function testValidateReturnsEmptyArrayForValidData(): void
    {
        $data = [
            'email' => 'test@example.com',
            'name'  => 'John',
        ];
        $rules = [
            'email' => 'required|valid_email',
            'name'  => 'required|min_length[2]',
        ];

        $errors = $this->service->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testValidateReturnsErrorsForInvalidData(): void
    {
        $data = [
            'email' => 'invalid-email',
            'name'  => '',
        ];
        $rules = [
            'email' => 'required|valid_email',
            'name'  => 'required|min_length[2]',
        ];

        $errors = $this->service->validate($data, $rules);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidateUsesCustomMessages(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => 'required'];
        $messages = ['email.required' => 'Custom email required message'];

        $errors = $this->service->validate($data, $rules, $messages);

        $this->assertEquals('Custom email required message', $errors['email']);
    }

    // ==================== getRules() ====================

    public function testGetRulesReturnsRulesForValidDomainAndAction(): void
    {
        $rules = $this->service->getRules('auth', 'login');

        $this->assertNotEmpty($rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function testGetRulesReturnsEmptyArrayForInvalidDomain(): void
    {
        $rules = $this->service->getRules('nonexistent', 'login');

        $this->assertEmpty($rules);
    }

    public function testGetRulesReturnsEmptyArrayForInvalidAction(): void
    {
        $rules = $this->service->getRules('auth', 'nonexistent');

        $this->assertEmpty($rules);
    }

    // ==================== getMessages() ====================

    public function testGetMessagesReturnsMessagesForValidDomainAndAction(): void
    {
        $messages = $this->service->getMessages('auth', 'login');

        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey('email.required', $messages);
    }

    public function testGetMessagesReturnsEmptyArrayForInvalidDomain(): void
    {
        $messages = $this->service->getMessages('nonexistent', 'login');

        $this->assertEmpty($messages);
    }

    // ==================== validateOrFail() ====================

    public function testValidateOrFailDoesNothingForValidData(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'secretpassword',
        ];

        // Should not throw exception
        $this->service->validateOrFail($data, 'auth', 'login');

        $this->assertTrue(true); // Reached here without exception
    }

    public function testValidateOrFailThrowsExceptionForInvalidData(): void
    {
        $data = [
            'email' => '',
            'password' => '',
        ];

        $this->expectException(ValidationException::class);

        $this->service->validateOrFail($data, 'auth', 'login');
    }

    public function testValidateOrFailDoesNothingForEmptyRules(): void
    {
        // Should not throw exception for unknown action (empty rules)
        $this->service->validateOrFail(['anything' => 'value'], 'auth', 'nonexistent');

        $this->assertTrue(true); // Reached here without exception
    }

    // ==================== get() ====================

    public function testGetReturnsBothRulesAndMessages(): void
    {
        $result = $this->service->get('auth', 'login');

        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertNotEmpty($result['rules']);
        $this->assertNotEmpty($result['messages']);
    }

    // ==================== Domain management ====================

    public function testHasDomainReturnsTrueForRegisteredDomain(): void
    {
        $this->assertTrue($this->service->hasDomain('auth'));
        $this->assertTrue($this->service->hasDomain('user'));
        $this->assertTrue($this->service->hasDomain('file'));
        $this->assertTrue($this->service->hasDomain('token'));
        $this->assertTrue($this->service->hasDomain('audit'));
    }

    public function testHasDomainReturnsFalseForUnregisteredDomain(): void
    {
        $this->assertFalse($this->service->hasDomain('nonexistent'));
    }

    public function testGetDomainsReturnsAllRegisteredDomains(): void
    {
        $domains = $this->service->getDomains();

        $this->assertContains('auth', $domains);
        $this->assertContains('user', $domains);
        $this->assertContains('file', $domains);
        $this->assertContains('token', $domains);
        $this->assertContains('audit', $domains);
    }

    public function testRegisterValidatorAddsNewDomain(): void
    {
        // Create a simple custom validator
        $customValidator = new class () extends BaseValidation {
            public function getRules(string $action): array
            {
                return $action === 'test' ? ['field' => 'required'] : [];
            }

            public function getMessages(string $action): array
            {
                return $action === 'test' ? ['field.required' => 'Field is required'] : [];
            }
        };

        $this->service->registerValidator('custom', $customValidator);

        $this->assertTrue($this->service->hasDomain('custom'));
        $this->assertEquals(['field' => 'required'], $this->service->getRules('custom', 'test'));
    }

    // ==================== Integration with different domains ====================

    public function testUserDomainValidation(): void
    {
        $rules = $this->service->getRules('user', 'store_admin');

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('role', $rules);
    }

    public function testFileDomainValidation(): void
    {
        $rules = $this->service->getRules('file', 'upload');

        $this->assertArrayHasKey('file', $rules);
    }

    public function testTokenDomainValidation(): void
    {
        $rules = $this->service->getRules('token', 'refresh');

        $this->assertArrayHasKey('refresh_token', $rules);
    }

    public function testAuditDomainValidation(): void
    {
        $rules = $this->service->getRules('audit', 'index');

        // Should include pagination rules
        $this->assertArrayHasKey('page', $rules);
        $this->assertArrayHasKey('per_page', $rules);
    }
}
