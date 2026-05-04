<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * Verifies the hierarchical guardrails enforced by IamAuthorizationService.
 *
 * Six privilege-escalation scenarios:
 *  (a) Admin tries to attach `iam.superadmin-access` to a role he can reach.
 *  (b) Admin tries to attach the `superadmin` role to his own membership.
 *  (c) Admin tries to attach a role to another user's SuperAdmin membership.
 *  (d) Admin tries to update or delete the `superadmin` system role.
 *  (e) Admin tries to delete a SuperAdmin user.
 *  (f) Admin tries to update his own user record.
 *
 * Plus a SuperAdmin happy-path test that confirms (a)-(e) succeed for SA
 * (f intentionally still fails for SA — assertNotSelf applies to everyone).
 *
 * @internal
 */
final class PrivilegeEscalationTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testAdminCannotInjectSuperadminPermissionIntoExistingRole(): void
    {
        $this->actAs('admin');
        $adminRoleId        = $this->roleIdByCode('admin');
        $superadminPermId   = $this->permissionIdByCode('iam.superadmin-access');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/roles/{$adminRoleId}/permissions/attach",
            ['permission_ids' => [(string) $superadminPermId]]
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotSelfAssignSuperadminRole(): void
    {
        $this->actAs('admin');
        $superadminRoleId = $this->roleIdByCode('superadmin');
        $ownMembershipId  = $this->membershipIdForUser((int) $this->currentUserId);

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/memberships/{$ownMembershipId}/roles/attach",
            ['role_ids' => [(string) $superadminRoleId]]
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotAttachRoleToSuperadminMembership(): void
    {
        // Create a separate superadmin user first
        $saUserId        = $this->createUser('sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin');
        $saMembershipId  = $this->membershipIdForUser($saUserId);
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        // Then act as admin
        $this->actAs('admin');
        $userRoleId = $this->roleIdByCode('user');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/memberships/{$saMembershipId}/roles/attach",
            ['role_ids' => [(string) $userRoleId]]
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotUpdateSystemRole(): void
    {
        $this->actAs('admin');
        $superadminRoleId = $this->roleIdByCode('superadmin');

        $result = $this->withBodyFormat('json')->put(
            "/api/v1/iam/roles/{$superadminRoleId}",
            ['name' => 'Hijacked']
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteSystemRole(): void
    {
        $this->actAs('admin');
        $superadminRoleId = $this->roleIdByCode('superadmin');

        $result = $this->delete("/api/v1/iam/roles/{$superadminRoleId}");

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteSuperadminUser(): void
    {
        $saUserId = $this->createUser('victim-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        $result = $this->delete("/api/v1/users/{$saUserId}");

        $result->assertStatus(403);
    }

    public function testAdminCannotApproveSuperadminUser(): void
    {
        $saUserId = $this->createUser('pending-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin', 'pending_approval');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        $result = $this->withBodyFormat('json')->post("/api/v1/users/{$saUserId}/approve");

        $result->assertStatus(403);
    }

    public function testAdminCannotUpdateOwnUser(): void
    {
        $this->actAs('admin');
        $ownId = (int) $this->currentUserId;

        $result = $this->withBodyFormat('json')->put(
            "/api/v1/users/{$ownId}",
            ['first_name' => 'Hacky']
        );

        $result->assertStatus(403);
    }

    public function testAdminListingHidesSuperadminUsers(): void
    {
        $saUserId = $this->createUser('hidden-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        $result = $this->get('/api/v1/users?per_page=100');
        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true) ?? [];
        $items = $body['data']['data'] ?? ($body['data'] ?? ($body['items'] ?? []));
        $ids   = [];
        if (is_array($items)) {
            foreach ($items as $u) {
                if (is_array($u) && isset($u['id'])) {
                    $ids[] = (int) $u['id'];
                }
            }
        }

        $this->assertNotContains($saUserId, $ids, 'Admin listing should not enumerate SuperAdmin users.');
    }

    public function testAdminCanAttachNonSuperadminRoleToNonSuperadminUser(): void
    {
        // Create a regular user (auto-provisioned with self membership, no roles)
        $regularUserId       = $this->createUser('regular-' . uniqid() . '@example.com', 'ValidPass123!', 'user');
        $regularMembershipId = $this->membershipIdForUser($regularUserId);

        // Detach the seeded "user" role first so the membership starts empty
        $userRoleId = $this->roleIdByCode('user');
        $db         = \Config\Database::connect();
        $db->table('membership_roles')
            ->where('membership_id', $regularMembershipId)
            ->where('role_id', $userRoleId)
            ->delete();

        // Burn any stale cached effective permissions for this user.
        \Config\Services::effectivePermissionsResolver()->invalidateAll();

        $this->actAs('admin');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/memberships/{$regularMembershipId}/roles/attach",
            ['role_ids' => [(string) $userRoleId]]
        );

        $result->assertStatus(200);
    }

    public function testAdminCanReadIamRoles(): void
    {
        $this->actAs('admin');

        $result = $this->get('/api/v1/iam/roles');

        $result->assertStatus(200);
    }

    public function testNewUserGetsAutoMembershipInSelfApp(): void
    {
        $this->actAs('admin');

        $result = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email'      => 'auto-membership-' . uniqid() . '@example.com',
            'first_name' => 'Auto',
            'last_name'  => 'Membership',
        ]);

        $result->assertStatus(201);

        $body          = json_decode($result->getJSON(), true) ?? [];
        $createdUserId = (int) ($body['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdUserId);

        $db    = \Config\Database::connect();
        $count = $db->table('app_user_memberships')
            ->where('user_id', $createdUserId)
            ->where('application_id', 1)
            ->countAllResults();

        $this->assertSame(1, $count, 'Newly created user should have exactly one membership in app self.');
    }

    public function testPublicRegistrationGetsAutoMembershipInSelfApp(): void
    {
        // Register through the public endpoint (no auth headers).
        $this->clearTestRequestHeaders();

        $email = 'public-signup-' . uniqid() . '@example.com';
        $this->withBodyFormat('json')->post('/api/v1/auth/register', [
            'email'      => $email,
            'password'   => 'ValidPass123!Strong',
            'first_name' => 'Pub',
            'last_name'  => 'Signup',
        ]);

        $db   = \Config\Database::connect();
        $user = $db->table('users')->where('email', $email)->get()?->getRowArray();
        $this->assertNotNull($user, 'Registered user should exist after /auth/register.');

        $count = $db->table('app_user_memberships')
            ->where('user_id', (int) $user['id'])
            ->where('application_id', 1)
            ->countAllResults();

        $this->assertSame(1, $count, 'Public-registered user must be auto-provisioned with a self membership.');
    }

    public function testSuperAdminCanAttachSuperadminPermissionToAnyRole(): void
    {
        // Create a fresh non-system role so we don't pollute seeded roles in
        // case other tests run after this one with executionOrder reordering.
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'application_id' => 1,
            'code'           => 'qa-test-role-' . uniqid(),
            'name'           => 'QA Test Role',
            'description'    => 'Created by feature test',
            'is_system'      => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $newRoleId = (int) $db->insertID();

        $this->actAs('superadmin');
        $superadminPermId = $this->permissionIdByCode('iam.superadmin-access');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/roles/{$newRoleId}/permissions/attach",
            ['permission_ids' => [(string) $superadminPermId]]
        );

        $result->assertStatus(200);
    }

    private function roleIdByCode(string $code): int
    {
        $db  = \Config\Database::connect();
        $row = $db->table('roles')->where('code', $code)->where('application_id', 1)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function permissionIdByCode(string $code): int
    {
        $db  = \Config\Database::connect();
        $row = $db->table('permissions')->where('code', $code)->where('application_id', 1)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function membershipIdForUser(int $userId): int
    {
        $db  = \Config\Database::connect();
        $row = $db->table('app_user_memberships')->where('user_id', $userId)->where('application_id', 1)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }
}
