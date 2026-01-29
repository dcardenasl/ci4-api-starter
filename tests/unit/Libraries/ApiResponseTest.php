<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\ApiResponse;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * ApiResponse Unit Tests
 *
 * Tests for centralized API response formatting library.
 */
class ApiResponseTest extends CIUnitTestCase
{
    public function testSuccessWithData(): void
    {
        $result = ApiResponse::success(['user' => 'test']);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(['user' => 'test'], $result['data']);
    }

    public function testSuccessWithMessage(): void
    {
        $result = ApiResponse::success(['id' => 1], 'User created');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('User created', $result['message']);
    }

    public function testSuccessWithMeta(): void
    {
        $result = ApiResponse::success([], null, ['count' => 10]);

        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(['count' => 10], $result['meta']);
    }

    public function testSuccessWithoutData(): void
    {
        $result = ApiResponse::success();

        $this->assertEquals('success', $result['status']);
        $this->assertArrayNotHasKey('data', $result);
    }

    public function testError(): void
    {
        $result = ApiResponse::error(['field' => 'error'], 'Failed');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed', $result['message']);
        $this->assertEquals(['field' => 'error'], $result['errors']);
    }

    public function testErrorWithString(): void
    {
        $result = ApiResponse::error('Something went wrong');

        $this->assertEquals(['general' => 'Something went wrong'], $result['errors']);
    }

    public function testErrorWithCode(): void
    {
        $result = ApiResponse::error(['field' => 'error'], 'Failed', 400);

        $this->assertEquals(400, $result['code']);
    }

    public function testPaginated(): void
    {
        $result = ApiResponse::paginated([1, 2, 3], 30, 2, 10);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result['meta']);

        $pagination = $result['meta']['pagination'];
        $this->assertEquals(30, $pagination['total']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(3, $pagination['last_page']);
        $this->assertEquals(11, $pagination['from']);
        $this->assertEquals(20, $pagination['to']);
    }

    public function testPaginatedFirstPage(): void
    {
        $result = ApiResponse::paginated([1, 2, 3], 30, 1, 10);

        $pagination = $result['meta']['pagination'];
        $this->assertEquals(1, $pagination['from']);
        $this->assertEquals(10, $pagination['to']);
    }

    public function testPaginatedLastPage(): void
    {
        $result = ApiResponse::paginated([1, 2, 3], 25, 3, 10);

        $pagination = $result['meta']['pagination'];
        $this->assertEquals(21, $pagination['from']);
        $this->assertEquals(25, $pagination['to']); // Not 30
    }

    public function testCreated(): void
    {
        $result = ApiResponse::created(['id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('created', strtolower($result['message']));
    }

    public function testCreatedWithCustomMessage(): void
    {
        $result = ApiResponse::created(['id' => 1], 'Custom message');

        $this->assertEquals('Custom message', $result['message']);
    }

    public function testDeleted(): void
    {
        $result = ApiResponse::deleted();

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('deleted', strtolower($result['message']));
    }

    public function testDeletedWithCustomMessage(): void
    {
        $result = ApiResponse::deleted('User removed');

        $this->assertEquals('User removed', $result['message']);
    }

    public function testValidationError(): void
    {
        $result = ApiResponse::validationError(['email' => 'Invalid']);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testValidationErrorWithCustomMessage(): void
    {
        $result = ApiResponse::validationError(['email' => 'Invalid'], 'Custom validation error');

        $this->assertEquals('Custom validation error', $result['message']);
    }

    public function testNotFound(): void
    {
        $result = ApiResponse::notFound('User not found');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('User not found', $result['message']);
    }

    public function testUnauthorized(): void
    {
        $result = ApiResponse::unauthorized();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testUnauthorizedWithCustomMessage(): void
    {
        $result = ApiResponse::unauthorized('Invalid token');

        $this->assertEquals('Invalid token', $result['message']);
    }

    public function testForbidden(): void
    {
        $result = ApiResponse::forbidden();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(403, $result['code']);
    }

    public function testForbiddenWithCustomMessage(): void
    {
        $result = ApiResponse::forbidden('Access denied');

        $this->assertEquals('Access denied', $result['message']);
    }

    public function testServerError(): void
    {
        $result = ApiResponse::serverError();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(500, $result['code']);
    }

    public function testServerErrorWithCustomMessage(): void
    {
        $result = ApiResponse::serverError('Database connection failed');

        $this->assertEquals('Database connection failed', $result['message']);
    }
}
