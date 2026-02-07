<?php

declare(strict_types=1);

namespace Tests\Unit\Validations;

use App\Validations\UserValidation;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * UserValidation Unit Tests
 *
 * Tests the user management validation rules.
 */
class UserValidationTest extends CIUnitTestCase
{
    protected UserValidation $validation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validation = new UserValidation();
    }

    public function testGetRulesReturnsIndexRulesWithPagination(): void
    {
        $rules = $this->validation->getRules('index');

        $this->assertArrayHasKey('page', $rules);
        $this->assertArrayHasKey('per_page', $rules);
        $this->assertArrayHasKey('sort_by', $rules);
        $this->assertArrayHasKey('sort_dir', $rules);
        $this->assertArrayHasKey('search', $rules);
    }

    public function testGetRulesReturnsShowRulesWithId(): void
    {
        $rules = $this->validation->getRules('show');

        $this->assertArrayHasKey('id', $rules);
        $this->assertStringContainsString('required', $rules['id']);
        $this->assertStringContainsString('is_natural_no_zero', $rules['id']);
    }

    public function testGetRulesReturnsStoreRules(): void
    {
        $rules = $this->validation->getRules('store');

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('last_name', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('role', $rules);
        $this->assertStringContainsString('required', $rules['email']);
        $this->assertStringContainsString('permit_empty', $rules['password']);
        $this->assertStringContainsString('permit_empty', $rules['role']);
        $this->assertStringContainsString('in_list[user,admin]', $rules['role']);
    }

    public function testGetRulesReturnsUpdateRulesWithOptionalFields(): void
    {
        $rules = $this->validation->getRules('update');

        $this->assertArrayHasKey('id', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('last_name', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('role', $rules);

        // ID is required
        $this->assertStringContainsString('required', $rules['id']);

        // Other fields are optional for update
        $this->assertStringContainsString('permit_empty', $rules['email']);
        $this->assertStringContainsString('permit_empty', $rules['password']);
    }

    public function testGetRulesReturnsDestroyRulesWithId(): void
    {
        $rules = $this->validation->getRules('destroy');

        $this->assertArrayHasKey('id', $rules);
        $this->assertStringContainsString('required', $rules['id']);
    }

    public function testGetRulesReturnsEmptyForUnknownAction(): void
    {
        $rules = $this->validation->getRules('unknown_action');

        $this->assertEmpty($rules);
    }

    public function testGetMessagesReturnsIndexMessages(): void
    {
        $messages = $this->validation->getMessages('index');

        $this->assertArrayHasKey('page.is_natural_no_zero', $messages);
        $this->assertArrayHasKey('per_page.is_natural_no_zero', $messages);
        $this->assertArrayHasKey('per_page.less_than_equal_to', $messages);
    }

    public function testGetMessagesReturnsShowMessages(): void
    {
        $messages = $this->validation->getMessages('show');

        $this->assertArrayHasKey('id.required', $messages);
        $this->assertArrayHasKey('id.is_natural_no_zero', $messages);
    }

    public function testGetMessagesReturnsStoreMessages(): void
    {
        $messages = $this->validation->getMessages('store');

        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('first_name.max_length', $messages);
        $this->assertArrayHasKey('last_name.max_length', $messages);
        $this->assertArrayHasKey('password.required', $messages);
        $this->assertArrayHasKey('role.in_list', $messages);
    }

    public function testGetReturnsRulesAndMessages(): void
    {
        $result = $this->validation->get('store');

        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertNotEmpty($result['rules']);
        $this->assertNotEmpty($result['messages']);
    }
}
