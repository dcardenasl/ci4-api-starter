<?php

declare(strict_types=1);

namespace Tests\Unit\Validations;

use App\Validations\AuthValidation;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AuthValidation Unit Tests
 *
 * Tests the authentication validation rules.
 */
class AuthValidationTest extends CIUnitTestCase
{
    protected AuthValidation $validation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validation = new AuthValidation();
    }

    public function testGetRulesReturnsLoginRules(): void
    {
        $rules = $this->validation->getRules('login');

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('required', $rules['email']);
        $this->assertStringContainsString('required', $rules['password']);
    }

    public function testGetRulesReturnsRegisterRules(): void
    {
        $rules = $this->validation->getRules('register');

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('last_name', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('valid_email_idn', $rules['email']);
        $this->assertStringContainsString('strong_password', $rules['password']);
    }

    public function testGetRulesReturnsForgotPasswordRules(): void
    {
        $rules = $this->validation->getRules('forgot_password');

        $this->assertArrayHasKey('email', $rules);
        $this->assertStringContainsString('required', $rules['email']);
        $this->assertStringContainsString('valid_email_idn', $rules['email']);
    }

    public function testGetRulesReturnsResetPasswordRules(): void
    {
        $rules = $this->validation->getRules('reset_password');

        $this->assertArrayHasKey('token', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('valid_token', $rules['token']);
        $this->assertStringContainsString('strong_password', $rules['password']);
    }

    public function testGetRulesReturnsVerifyEmailRules(): void
    {
        $rules = $this->validation->getRules('verify_email');

        $this->assertArrayHasKey('token', $rules);
        $this->assertArrayHasKey('email', $rules);
    }

    public function testGetRulesReturnsRefreshRules(): void
    {
        $rules = $this->validation->getRules('refresh');

        $this->assertArrayHasKey('refresh_token', $rules);
        $this->assertStringContainsString('required', $rules['refresh_token']);
    }

    public function testGetRulesReturnsEmptyForUnknownAction(): void
    {
        $rules = $this->validation->getRules('unknown_action');

        $this->assertEmpty($rules);
    }

    public function testGetMessagesReturnsLoginMessages(): void
    {
        $messages = $this->validation->getMessages('login');

        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('password.required', $messages);
    }

    public function testGetMessagesReturnsRegisterMessages(): void
    {
        $messages = $this->validation->getMessages('register');

        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('first_name.max_length', $messages);
        $this->assertArrayHasKey('last_name.max_length', $messages);
        $this->assertArrayHasKey('password.required', $messages);
        $this->assertArrayHasKey('password.strong_password', $messages);
    }

    public function testGetReturnsRulesAndMessages(): void
    {
        $result = $this->validation->get('login');

        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertNotEmpty($result['rules']);
        $this->assertNotEmpty($result['messages']);
    }

    public function testHasActionReturnsTrueForExistingAction(): void
    {
        $this->assertTrue($this->validation->hasAction('login'));
        $this->assertTrue($this->validation->hasAction('register'));
        $this->assertTrue($this->validation->hasAction('forgot_password'));
    }

    public function testHasActionReturnsFalseForUnknownAction(): void
    {
        $this->assertFalse($this->validation->hasAction('nonexistent'));
    }
}
