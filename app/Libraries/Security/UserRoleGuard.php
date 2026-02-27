<?php

declare(strict_types=1);

namespace App\Libraries\Security;

use App\Exceptions\AuthorizationException;

/**
 * User Role Guard
 *
 * Centralizes business rules for role assignments and user management permissions.
 */
class UserRoleGuard
{
    /**
     * Assert that an actor can assign a specific role to a new or existing user.
     */
    public function assertCanAssignRole(string $actorRole, string $requestedRole): void
    {
        if ($actorRole === 'superadmin') {
            return;
        }

        if ($actorRole === 'admin' && in_array($requestedRole, ['admin', 'superadmin'], true)) {
            throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
        }

        if ($actorRole === 'user' && $requestedRole !== 'user') {
            throw new AuthorizationException(lang('Auth.insufficientPermissions'));
        }
    }

    /**
     * Assert that an actor can manage (edit/delete) a target user.
     */
    public function assertCanManageTarget(string $actorRole, ?int $actorId, int $targetId, string $targetRole): void
    {
        if ($actorRole === 'superadmin') {
            return;
        }

        // Admins cannot manage other admins or superadmins (except themselves)
        if ($actorRole === 'admin') {
            if (in_array($targetRole, ['admin', 'superadmin'], true) && ($actorId === null || $targetId !== $actorId)) {
                throw new AuthorizationException(lang('Users.adminCannotManagePrivileged'));
            }
            return;
        }

        // Regular users can only manage themselves
        if ($actorId !== null && $targetId !== $actorId) {
            throw new AuthorizationException(lang('Auth.insufficientPermissions'));
        }
    }

    /**
     * Assert that an actor can change a user's role.
     */
    public function assertCanChangeRole(string $actorRole, string $currentRole, string $requestedRole): void
    {
        if ($actorRole === 'superadmin') {
            return;
        }

        if ($actorRole === 'admin') {
            if ($requestedRole === 'superadmin') {
                throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
            }

            if ($requestedRole === 'admin' && $currentRole !== 'admin') {
                throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
            }
            return;
        }

        if ($currentRole !== $requestedRole) {
            throw new AuthorizationException(lang('Auth.insufficientPermissions'));
        }
    }
}
