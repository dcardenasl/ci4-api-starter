<?php

declare(strict_types=1);

namespace App\Filters;

use App\HTTP\ApiRequest;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Test Auth Filter
 *
 * Minimal filter for testing that trusts the ContextHolder.
 * Used to avoid JWT complexities during integration tests.
 */
class TestAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT !== 'testing') {
            return $request;
        }

        $context = \App\Libraries\ContextHolder::get();

        // If test established context, populate request and allow
        if ($context !== null && $context->userId !== null) {
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $context->userId, (string) $context->role);
            }
            return $request;
        }

        // Otherwise, fall back to standard headers for tests that don't use actAs correctly
        $testUserId = $request->getHeaderLine('X-Test-User-Id');
        if ($testUserId !== '') {
            $testUserRole = $request->getHeaderLine('X-Test-User-Role') ?: 'user';
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $testUserId, $testUserRole);
            }
            return $request;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader !== '') {
            return (new JwtAuthFilter())->before($request, $arguments);
        }

        // If no identity found, return 401
        return Services::response()
            ->setJSON(['status' => 'error', 'message' => 'Unauthorized (Test Auth)'])
            ->setStatusCode(401);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
