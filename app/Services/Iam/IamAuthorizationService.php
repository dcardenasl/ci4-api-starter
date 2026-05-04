<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\SecurityContext;
use App\Exceptions\AuthorizationException;
use App\Services\System\SecurityAuditLogger;
use Config\Database;

/**
 * Hierarchical authorization rules for IAM operations.
 *
 * SuperAdmin = actor whose effective permissions include `iam.superadmin-access`.
 * SuperAdmin bypasses every assert except `assertNotSelf` (which intentionally
 * applies to everyone to prevent accidental lock-out).
 *
 * Non-SuperAdmin actors:
 *   - cannot grant a permission they do not own (`assertCanGrantPermissions`)
 *   - cannot grant a role whose permissions exceed their own
 *     (`assertCanGrantRoles`)
 *   - cannot modify roles flagged `is_system=1` (`assertCanModifyRole`)
 *   - cannot operate on subjects who are SuperAdmin
 *     (`assertCanActOnSubject`)
 *   - cannot operate on themselves (`assertNotSelf`)
 *
 * Every denial is audited via `SecurityAuditLogger` and surfaced as
 * `AuthorizationException` (HTTP 403) with an i18n message from `Iam.php`.
 */
class IamAuthorizationService
{
    public const SUPERADMIN_PERMISSION    = 'iam.superadmin-access';
    public const ADMIN_PERMISSION         = 'iam.admin-access';
    public const DEFAULT_APPLICATION_ID   = 1;

    public function __construct(
        private readonly EffectivePermissionsResolver $resolver,
        private readonly SecurityAuditLogger $audit,
    ) {
    }

    public function isSuperAdmin(?SecurityContext $context, int $applicationId = self::DEFAULT_APPLICATION_ID): bool
    {
        if ($context === null || $context->user_id === null) {
            return false;
        }

        if ($context->permissions !== []) {
            return in_array(self::SUPERADMIN_PERMISSION, $context->permissions, true);
        }

        return in_array(self::SUPERADMIN_PERMISSION, $this->resolver->resolve($context->user_id, $applicationId), true);
    }

    /**
     * @return list<string>
     */
    public function actorPermissions(?SecurityContext $context, int $applicationId = self::DEFAULT_APPLICATION_ID): array
    {
        if ($context === null || $context->user_id === null) {
            return [];
        }

        if ($context->permissions !== []) {
            return $context->permissions;
        }

        return $this->resolver->resolve($context->user_id, $applicationId);
    }

    /**
     * @return list<string>
     */
    public function subjectPermissions(int $subjectUserId, int $applicationId = self::DEFAULT_APPLICATION_ID): array
    {
        return $this->resolver->resolve($subjectUserId, $applicationId);
    }

    /**
     * Block grants of permissions the actor does not already hold.
     *
     * @param array<int, int> $permissionIds
     */
    public function assertCanGrantPermissions(?SecurityContext $context, array $permissionIds, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        if ($permissionIds === [] || $this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $actorPerms = $this->actorPermissions($context, $applicationId);
        $codes      = $this->resolvePermissionCodes($permissionIds);
        $unowned    = array_values(array_diff($codes, $actorPerms));

        if ($unowned !== []) {
            $this->deny($context, 'cannotGrantUnownedPermission', [
                'unowned'        => $unowned,
                'permission_ids' => array_values($permissionIds),
            ]);
        }
    }

    /**
     * Block grants of roles whose permission set exceeds the actor's own.
     *
     * @param array<int, int> $roleIds
     */
    public function assertCanGrantRoles(?SecurityContext $context, array $roleIds, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        if ($roleIds === [] || $this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $actorPerms = $this->actorPermissions($context, $applicationId);
        $codes      = $this->resolveRolePermissionCodes($roleIds);
        $unowned    = array_values(array_diff($codes, $actorPerms));

        if ($unowned !== []) {
            $this->deny($context, 'cannotGrantUnownedPermission', [
                'unowned'  => $unowned,
                'role_ids' => array_values($roleIds),
            ]);
        }
    }

    public function assertCanModifyRole(?SecurityContext $context, int $roleId, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        if ($this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $db   = Database::connect();
        $row  = $db->table('roles')->where('id', $roleId)->select('is_system')->get();
        $data = $row === false ? null : $row->getRowArray();

        if ($data !== null && (int) ($data['is_system'] ?? 0) === 1) {
            $this->deny($context, 'cannotModifySystemRole', ['role_id' => $roleId]);
        }
    }

    /**
     * Self-protection. Applies to every actor, including SuperAdmin, to
     * prevent accidental lock-out (e.g. removing one's own superadmin role).
     */
    public function assertNotSelf(?SecurityContext $context, int $subjectUserId): void
    {
        if ($context !== null && $context->user_id === $subjectUserId) {
            $this->deny($context, 'cannotModifySelf', ['subject_id' => $subjectUserId]);
        }
    }

    public function assertCanActOnSubject(?SecurityContext $context, int $subjectUserId, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        if ($this->isSuperAdmin($context, $applicationId)) {
            return;
        }

        $subjectPerms = $this->subjectPermissions($subjectUserId, $applicationId);
        if (in_array(self::SUPERADMIN_PERMISSION, $subjectPerms, true)) {
            $this->deny($context, 'cannotActOnSuperAdmin', ['subject_id' => $subjectUserId]);
        }
    }

    /**
     * Convenience for the common "modifying user/membership X" flow.
     */
    public function assertCanModifySubject(?SecurityContext $context, int $subjectUserId, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        $this->assertNotSelf($context, $subjectUserId);
        $this->assertCanActOnSubject($context, $subjectUserId, $applicationId);
    }

    public function assertSuperAdmin(?SecurityContext $context, int $applicationId = self::DEFAULT_APPLICATION_ID): void
    {
        if (! $this->isSuperAdmin($context, $applicationId)) {
            $this->deny($context, 'cannotPerformSuperAdminOperation', []);
        }
    }

    /**
     * @param array<int, int> $permissionIds
     * @return list<string>
     */
    private function resolvePermissionCodes(array $permissionIds): array
    {
        $db    = Database::connect();
        $query = $db->table('permissions')
            ->whereIn('id', $permissionIds)
            ->select('code')
            ->get();

        if ($query === false) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (array $r) => (string) $r['code'],
            $query->getResultArray()
        )));
    }

    /**
     * @param array<int, int> $roleIds
     * @return list<string>
     */
    private function resolveRolePermissionCodes(array $roleIds): array
    {
        $db    = Database::connect();
        $query = $db->table('role_permissions rp')
            ->select('p.code')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->get();

        if ($query === false) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (array $r) => (string) $r['code'],
            $query->getResultArray()
        )));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function deny(?SecurityContext $context, string $messageKey, array $details): never
    {
        $this->audit->logAuthorizationDeniedFromContext(
            'iam.authorization.denied',
            array_merge(['rule' => $messageKey], $details),
            $context
        );

        $fullKey = 'Iam.' . $messageKey;

        throw new AuthorizationException(lang($fullKey));
    }
}
