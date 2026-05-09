<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;

/**
 * Permission-Based Access Control Filter
 *
 * Enforces fine-grained permission checks on protected routes by reading the
 * `scope` claim from the decoded JWT (already populated into ApiRequest /
 * ContextHolder by JwtAuthFilter or TestAuthFilter).
 *
 * Argument: required permission code, e.g. `permission:users.write`.
 *
 * Note: permission codes use `.` as the resource/action separator (not `:`)
 * because CodeIgniter splits filter strings on `:` and would discard anything
 * after the second colon (`permission:users:write` would be parsed with
 * `users` as the only argument).
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';

        $context = ContextHolder::get();
        $actorId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $actorId ??= $context?->user_id;

        $permissions = $request instanceof ApiRequest ? $request->getAuthPermissions() : [];
        if ($permissions === [] && $context !== null) {
            $permissions = $context->permissions;
        }

        $securityAuditLogger = Services::securityAuditLogger();

        // A populated SecurityContext means JwtAuthFilter (or TestAuthFilter) already
        // authenticated the caller. Service tokens (sub: service:<code>) have no uid
        // but carry a valid scope — they must reach the permission check below and
        // receive 403 if the required code is missing, not 401.
        $isAuthenticated = $context !== null || $actorId !== null;

        if (! $isAuthenticated) {
            $securityAuditLogger->logAuthorizationDeniedFromRequest($request, $required, null, null);

            return Services::response()
                ->setJSON(ApiResponse::unauthorized(lang('Auth.authRequired')))
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if ($required === '' || ! in_array($required, $permissions, true)) {
            $securityAuditLogger->logAuthorizationDeniedFromRequest($request, $required, null, $actorId);

            return Services::response()
                ->setJSON(ApiResponse::forbidden(lang('Auth.insufficientPermissions')))
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return $response;
    }
}
