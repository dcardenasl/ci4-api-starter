<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Role-Based Access Control Filter
 *
 * Enforces role-based permissions on protected routes.
 * Checks if the authenticated user has sufficient role level to access the resource.
 */
class RoleAuthorizationFilter implements FilterInterface
{
    /**
     * Role hierarchy (higher number = more permissions)
     */
    private const ROLE_HIERARCHY = [
        'user'  => 0,
        'admin' => 10,
    ];

    /**
     * Check if user has required role before allowing access
     *
     * @param RequestInterface $request
     * @param array|null $arguments First argument should be the required role (e.g., ['admin'])
     * @return ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $requiredRole = $arguments[0] ?? 'user';
        $userRole = $request->userRole ?? null;

        if (!$userRole) {
            return Services::response()
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication required',
                ])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (!$this->hasPermission($userRole, $requiredRole)) {
            return Services::response()
                ->setJSON([
                    'success' => false,
                    'message' => 'Insufficient permissions',
                ])
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }
    }

    /**
     * After filter (not used)
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Not used
    }

    /**
     * Check if user role has permission to access required role
     *
     * Uses hierarchical role system where higher levels inherit lower permissions.
     *
     * @param string $userRole Current user's role
     * @param string $requiredRole Required role for the resource
     * @return bool True if user has permission
     */
    private function hasPermission(string $userRole, string $requiredRole): bool
    {
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
