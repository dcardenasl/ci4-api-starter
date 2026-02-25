<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * ApiTestCase
 *
 * Base class for API feature tests.
 * Automatically handles request state isolation between multiple calls in a single test.
 */
abstract class ApiTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var bool Whether to refresh the database for each test
     */
    protected $refresh = true;

    /**
     * @var string The namespace for migrations
     */
    protected $namespace = 'App';

    /**
     * Reset the request and other services before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetState();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        $this->resetState();
        parent::tearDown();
    }

    /**
     * Resets the request state. Use this between consecutive API calls
     * in the same test method to ensure complete isolation.
     */
    protected function resetRequest(): void
    {
        $this->resetState();
    }

    /**
     * Resets PHP globals and CodeIgniter shared services to ensure
     * a clean state for the next request.
     */
    protected function resetState(): void
    {
        // Clear PHP globals that CI4's IncomingRequest might use
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
        $_REQUEST = [];

        // Reset the shared 'request' service instance
        Services::resetSingle('request');

        // Reset the $request property in FeatureTestTrait to force it
        // to create a new one for the next call
        $this->request = null;
    }

    /**
     * Helper to get a clean JSON response body as an array.
     */
    protected function getResponseJson($result): array
    {
        return json_decode($result->getJSON(), true) ?? [];
    }
}
