<?php

declare(strict_types=1);

namespace App\Filters;

use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
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
        if ($context !== null && $context->user_id !== null) {
            $permissions = $context->permissions;
            if ($permissions === [] && $context->user_role !== null && $context->user_role !== '') {
                $permissions = \App\Support\TestPermissionResolver::permissionsForRole($context->user_role);
                if ($permissions !== []) {
                    \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext(
                        $context->user_id,
                        $context->user_role,
                        $context->metadata,
                        $permissions
                    ));
                }
            }
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $context->user_id, (string) $context->user_role, $permissions);
            }
            return $request;
        }

        // Otherwise, fall back to standard headers for tests that don't use actAs correctly
        $testUserId = $request->getHeaderLine('X-Test-User-Id');
        if ($testUserId !== '') {
            $testUserRole = $request->getHeaderLine('X-Test-User-Role') ?: 'user';
            $permissions = \App\Support\TestPermissionResolver::permissionsForRole($testUserRole);
            \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext(
                (int) $testUserId,
                (string) $testUserRole,
                [],
                $permissions
            ));
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $testUserId, $testUserRole, $permissions);
            }
            return $request;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader !== '') {
            return (new JwtAuthFilter())->before($request, $arguments);
        }

        // If no identity found, return 401
        return Services::response()
            ->setJSON(ApiResponse::unauthorized(lang('Auth.authRequired')))
            ->setStatusCode(401);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
