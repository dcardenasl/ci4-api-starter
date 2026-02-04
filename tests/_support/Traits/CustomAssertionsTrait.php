<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Custom Assertions Trait
 *
 * Provides reusable assertion helpers for API response testing.
 * Reduces code duplication and enforces consistent response validation.
 *
 * Usage:
 * ```php
 * class MyServiceTest extends CIUnitTestCase
 * {
 *     use CustomAssertionsTrait;
 *
 *     public function testSomething(): void
 *     {
 *         $result = $this->service->someMethod();
 *         $this->assertSuccessResponse($result, 'token');
 *     }
 * }
 * ```
 */
trait CustomAssertionsTrait
{
    /**
     * Assert that response has success status and contains data
     *
     * @param array       $result  The service response array
     * @param string|null $dataKey Optional specific key to check in data array
     */
    protected function assertSuccessResponse(array $result, ?string $dataKey = null): void
    {
        $this->assertEquals('success', $result['status'], 'Response status should be success');
        $this->assertArrayHasKey('data', $result, 'Response should contain data key');

        if ($dataKey !== null) {
            $this->assertArrayHasKey($dataKey, $result['data'], "Data should contain '{$dataKey}' key");
        }
    }

    /**
     * Assert that response has error status and contains errors
     *
     * @param array       $result   The service response array
     * @param string|null $errorKey Optional specific error field to check
     */
    protected function assertErrorResponse(array $result, ?string $errorKey = null): void
    {
        $this->assertEquals('error', $result['status'], 'Response status should be error');
        $this->assertArrayHasKey('errors', $result, 'Response should contain errors key');

        if ($errorKey !== null) {
            $this->assertArrayHasKey($errorKey, $result['errors'], "Errors should contain '{$errorKey}' field");
        }
    }

    /**
     * Assert that response has validation error with specific fields
     *
     * @param array $result         The service response array
     * @param array $expectedFields Array of field names that should have errors
     */
    protected function assertValidationErrorResponse(array $result, array $expectedFields): void
    {
        $this->assertEquals('error', $result['status'], 'Response status should be error');
        $this->assertArrayHasKey('errors', $result, 'Response should contain errors key');

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey(
                $field,
                $result['errors'],
                "Validation errors should contain '{$field}' field"
            );
        }
    }

    /**
     * Assert that response is paginated with correct structure
     *
     * @param array $result The service response array
     */
    protected function assertPaginatedResponse(array $result): void
    {
        $this->assertEquals('success', $result['status'], 'Response status should be success');
        $this->assertArrayHasKey('data', $result, 'Response should contain data');
        $this->assertArrayHasKey('meta', $result, 'Response should contain meta');

        $meta = $result['meta'];
        $this->assertArrayHasKey('total', $meta, 'Meta should contain total count');
        $this->assertArrayHasKey('page', $meta, 'Meta should contain current page');
        $this->assertArrayHasKey('perPage', $meta, 'Meta should contain per page limit');

        $this->assertIsInt($meta['total'], 'Total should be integer');
        $this->assertIsInt($meta['page'], 'Page should be integer');
        $this->assertIsInt($meta['perPage'], 'PerPage should be integer');
    }

    /**
     * Assert that response has error status with specific HTTP code
     *
     * @param array $result       The service response array
     * @param int   $expectedCode Expected HTTP status code
     */
    protected function assertErrorResponseWithCode(array $result, int $expectedCode): void
    {
        $this->assertEquals('error', $result['status'], 'Response status should be error');
        $this->assertArrayHasKey('code', $result, 'Response should contain HTTP code');
        $this->assertEquals($expectedCode, $result['code'], "HTTP code should be {$expectedCode}");
    }

    /**
     * Assert that response contains specific message
     *
     * @param array  $result          The service response array
     * @param string $expectedMessage Expected message (exact match)
     */
    protected function assertResponseMessage(array $result, string $expectedMessage): void
    {
        $this->assertArrayHasKey('message', $result, 'Response should contain message');
        $this->assertEquals($expectedMessage, $result['message'], 'Message should match expected value');
    }

    /**
     * Assert that response message contains specific substring
     *
     * @param array  $result            The service response array
     * @param string $expectedSubstring Expected substring in message
     */
    protected function assertResponseMessageContains(array $result, string $expectedSubstring): void
    {
        $this->assertArrayHasKey('message', $result, 'Response should contain message');
        $this->assertStringContainsString(
            $expectedSubstring,
            $result['message'],
            "Message should contain '{$expectedSubstring}'"
        );
    }

    /**
     * Assert that data array is empty
     *
     * @param array $result The service response array
     */
    protected function assertEmptyDataResponse(array $result): void
    {
        $this->assertEquals('success', $result['status'], 'Response status should be success');
        $this->assertArrayHasKey('data', $result, 'Response should contain data key');
        $this->assertEmpty($result['data'], 'Data should be empty');
    }

    /**
     * Assert that data array has specific count
     *
     * @param array $result        The service response array
     * @param int   $expectedCount Expected number of items in data
     */
    protected function assertDataCount(array $result, int $expectedCount): void
    {
        $this->assertEquals('success', $result['status'], 'Response status should be success');
        $this->assertArrayHasKey('data', $result, 'Response should contain data key');
        $this->assertIsArray($result['data'], 'Data should be an array');
        $this->assertCount($expectedCount, $result['data'], "Data should contain {$expectedCount} items");
    }
}
