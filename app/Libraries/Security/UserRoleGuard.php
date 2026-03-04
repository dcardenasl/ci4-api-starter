<?php

declare(strict_types=1);

namespace App\Libraries\Security;

use App\Exceptions\AuthorizationException;
use App\Libraries\ContextHolder;
use App\Services\System\SecurityAuditLogger;
use Config\Services;

/**
 * User Role Guard
 *
 * Centralizes business rules for role assignments and user management permissions.
 */
class UserRoleGuard
{
    public function __construct(
        private ?SecurityAuditLogger $securityAuditLogger = null
    ) {
    }

    /**
     * Assert that an actor can assign a specific role to a new or existing user.
     */
    public function assertCanAssignRole(string $actorRole, string $requestedRole): void
    {
        if ($actorRole === 'superadmin') {
            return;
        }

        if ($actorRole === 'admin' && in_array($requestedRole, ['admin', 'superadmin'], true)) {
            $this->logAuthorizationDenied('authorization_denied_role', [
                'actor_role' => $actorRole,
                'requested_role' => $requestedRole,
                'rule' => 'admin_cannot_assign_privileged_role',
            ]);
            throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
        }

        if ($actorRole === 'user' && $requestedRole !== 'user') {
            $this->logAuthorizationDenied('authorization_denied_role', [
                'actor_role' => $actorRole,
                'requested_role' => $requestedRole,
                'rule' => 'user_cannot_assign_non_user',
            ]);
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
                $this->logAuthorizationDenied('authorization_denied_resource', [
                    'actor_role' => $actorRole,
                    'actor_id' => $actorId,
                    'target_id' => $targetId,
                    'target_role' => $targetRole,
                    'rule' => 'admin_cannot_manage_privileged',
                ]);
                throw new AuthorizationException(lang('Users.adminCannotManagePrivileged'));
            }
            return;
        }

        // Regular users can only manage themselves
        if ($actorId !== null && $targetId !== $actorId) {
            $this->logAuthorizationDenied('authorization_denied_resource', [
                'actor_role' => $actorRole,
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'target_role' => $targetRole,
                'rule' => 'user_can_only_manage_self',
            ]);
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
                $this->logAuthorizationDenied('authorization_denied_role', [
                    'actor_role' => $actorRole,
                    'current_role' => $currentRole,
                    'requested_role' => $requestedRole,
                    'rule' => 'admin_cannot_assign_superadmin',
                ]);
                throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
            }

            if ($requestedRole === 'admin' && $currentRole !== 'admin') {
                $this->logAuthorizationDenied('authorization_denied_role', [
                    'actor_role' => $actorRole,
                    'current_role' => $currentRole,
                    'requested_role' => $requestedRole,
                    'rule' => 'admin_cannot_elevate_to_admin',
                ]);
                throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
            }
            return;
        }

        if ($currentRole !== $requestedRole) {
            $this->logAuthorizationDenied('authorization_denied_role', [
                'actor_role' => $actorRole,
                'current_role' => $currentRole,
                'requested_role' => $requestedRole,
                'rule' => 'user_cannot_change_role',
            ]);
            throw new AuthorizationException(lang('Auth.insufficientPermissions'));
        }
    }

    private function logAuthorizationDenied(string $action, array $details): void
    {
        $logger = $this->securityAuditLogger ?? Services::securityAuditLogger();
        $logger->logAuthorizationDeniedFromContext($action, $details, ContextHolder::get());
    }
}
