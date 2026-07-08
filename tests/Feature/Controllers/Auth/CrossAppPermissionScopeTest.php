<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use App\Models\UserModel;
use Config\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\Support\ApiTestCase;

/**
 * Feature-level coverage for WBS-BP-08: cross-app effective permission
 * resolution embedded in the JWT `scope` claim.
 *
 * The hub is a hub-and-spoke system: `applications` registers every app,
 * `permissions.application_id` scopes each permission to one of them, but
 * before this change, login/refresh hardcoded `APPLICATION_ID = 1` (the
 * hub's own "self" app) when building the token's `scope` claim. A second
 * registered application (e.g. a Domain app) would never see its own
 * permissions land in the user's JWT.
 *
 * These tests hit the real HTTP login/refresh endpoints (not the resolver in
 * isolation) with a multi-application fixture, and assert the *actual*
 * decoded JWT `scope` claim contains the union of permissions across both
 * registered applications.
 */
class CrossAppPermissionScopeTest extends ApiTestCase
{
    private UserModel $userModel;
    private \CodeIgniter\Database\ConnectionInterface $testDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
        $this->testDb = \Config\Database::connect();

        // ApiTestCase::resetCacheState() replaces the shared `cache` service
        // with a fresh CodeIgniter\Test\Mock\MockCache instance every test,
        // but `effectivePermissionsResolver()` is a process-lifetime
        // getSharedInstance() singleton that captured whatever cache
        // instance existed when it was FIRST constructed. Across a full
        // suite run, DB row ids restart from 1 for every test class
        // (truncate + sqlite_sequence reset), so a different test class's
        // user can legitimately reuse the same numeric id — and, without
        // this, could observe that OLD user's stale cached resolveAll()
        // result. Explicitly busting the resolver's OWN cache (not a fresh
        // Services::cache() we might not share) guarantees each test here
        // starts from a real DB read.
        Services::effectivePermissionsResolver()->invalidateAll();
    }

    public function testLoginJwtScopeIsUnionOfPermissionsAcrossTwoRegisteredApplications(): void
    {
        $appTwo = $this->insertApp('domain-app-login');
        $role   = $this->insertRole('cross-app-login-role');

        $permApp1 = $this->insertPerm(1, 'cross-login.hub-only');
        $permApp2 = $this->insertPerm($appTwo, 'cross-login.domain-only');

        $this->attachPermToRole($role, $permApp1);
        $this->attachPermToRole($role, $permApp2);

        $email = 'cross-app-login@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createActiveUser($email, $password);
        $this->assignRoleToUser($userId, $role);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email'    => $email,
                'password' => $password,
            ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $accessToken = $json['data']['access_token'];

        // The response envelope's `user.permissions` field must carry the
        // union too — it's the same shape consumed by the frontend for UI
        // gating and must not diverge from the JWT scope.
        $this->assertContains('cross-login.hub-only', $json['data']['user']['permissions']);
        $this->assertContains('cross-login.domain-only', $json['data']['user']['permissions']);

        $decoded = $this->decodeJwt($accessToken);
        $scope = (array) $decoded->scope;

        $this->assertContains(
            'cross-login.hub-only',
            $scope,
            'JWT scope must still contain permissions from the hub\'s own (app 1) application'
        );
        $this->assertContains(
            'cross-login.domain-only',
            $scope,
            'JWT scope must contain permissions granted for a SECOND registered application — this is the whole point of resolveAll()'
        );
    }

    public function testRefreshJwtScopeIsUnionOfPermissionsAcrossTwoRegisteredApplications(): void
    {
        $appTwo = $this->insertApp('domain-app-refresh');
        $role   = $this->insertRole('cross-app-refresh-role');

        $permApp1 = $this->insertPerm(1, 'cross-refresh.hub-only');
        $permApp2 = $this->insertPerm($appTwo, 'cross-refresh.domain-only');

        $this->attachPermToRole($role, $permApp1);
        $this->attachPermToRole($role, $permApp2);

        $email = 'cross-app-refresh@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createActiveUser($email, $password);
        $this->assignRoleToUser($userId, $role);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email'    => $email,
                'password' => $password,
            ]);
        $loginResult->assertStatus(200);
        $loginJson = json_decode($loginResult->getJSON(), true);
        $refreshTokenPlaintext = $loginJson['data']['refresh_token'];

        // NOTE ON TEST STRATEGY: this repo's `tests` DB group is hardcoded to
        // SQLite3 (app/Config/Database.php), and
        // `RefreshTokenModel::findActiveForUpdate()` appends a raw `FOR
        // UPDATE` clause — invalid SQLite syntax. That is a pre-existing,
        // unrelated bug: it makes POST /api/v1/auth/refresh 401 via the real
        // HTTP endpoint for EVERY request in this test environment, before
        // resolveAll() is even reached (reproduced with a bare user + no
        // custom roles, confirmed unrelated to this task's change). Hitting
        // the model directly (outside HTTP) reproduces the identical
        // DatabaseException, proving it is not an HTTP-layer or
        // resolveAll()-related issue.
        //
        // To still exercise the REAL `RefreshTokenService::refreshAccessToken()`
        // production code (the exact call site this task changed:
        // `$this->permissionsResolver->resolveAll((int) $user->id)`), this
        // test wires the service by hand with every real, DI-configured
        // collaborator (JwtService, UserModel, UserAccountGuard,
        // EffectivePermissionsResolver — all hitting the real test DB) and
        // substitutes only the `RefreshTokenModel`'s lock-row lookup with a
        // stand-in that returns the token row PHP already confirmed exists
        // (proven above via the real HTTP login call, which itself performs
        // a real, unmocked `INSERT` through the same model).
        $tokenHash = \dcardenasl\Ci4ApiCore\Security\Hasher::token($refreshTokenPlaintext);
        $tokenRow = $this->testDb->table('refresh_tokens')->where('token', $tokenHash)->get()->getRowObject();
        $this->assertNotNull($tokenRow, 'The refresh token issued by the real login endpoint must exist in refresh_tokens');

        $stubRefreshTokenModel = $this->createStub(\App\Models\RefreshTokenModel::class);
        $stubRefreshTokenModel->method('findActiveForUpdate')->willReturn($tokenRow);
        $stubRefreshTokenModel->method('revokeToken')->willReturn(true);
        $stubRefreshTokenModel->method('insert')->willReturn(1);

        $refreshService = new \App\Services\Tokens\RefreshTokenService(
            $stubRefreshTokenModel,
            Services::jwtService(),
            Services::userModel(),
            Services::userAccountGuard(),
            Services::effectivePermissionsResolver()
        );

        $refreshRequest = new \App\DTO\Request\Identity\RefreshTokenRequestDTO(
            ['refresh_token' => $refreshTokenPlaintext],
            Services::validation()
        );

        $tokenResponse = $refreshService->refreshAccessToken($refreshRequest);

        $decoded = $this->decodeJwt($tokenResponse->access_token);
        $scope = (array) $decoded->scope;

        $this->assertContains('cross-refresh.hub-only', $scope);
        $this->assertContains(
            'cross-refresh.domain-only',
            $scope,
            'A refreshed access token must also carry the cross-app union, not just the initial login token'
        );
    }

    /**
     * Regression guard (critical requirement #5 in WBS-BP-08): a user with
     * permissions ONLY in app 1 must keep getting exactly those via the real
     * login endpoint — resolveAll() must not leak permissions from OTHER
     * users' applications, and must not change shape for single-app users.
     */
    public function testLoginJwtScopeForSingleAppUserIsUnaffected(): void
    {
        $appTwo = $this->insertApp('domain-app-unrelated');
        $singleAppRole = $this->insertRole('single-app-only-role');
        $otherAppRole  = $this->insertRole('other-app-only-role');

        $permApp1 = $this->insertPerm(1, 'single-app.only-perm');
        $permApp2 = $this->insertPerm($appTwo, 'other-app.only-perm');

        $this->attachPermToRole($singleAppRole, $permApp1);
        $this->attachPermToRole($otherAppRole, $permApp2);

        // Second, unrelated user holds the app-2-only role — must not leak
        // into the first user's token.
        $otherUserId = $this->createActiveUser('other-app-user@example.com', 'ValidPass123!');
        $this->assignRoleToUser($otherUserId, $otherAppRole);

        $email = 'single-app-user@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createActiveUser($email, $password);
        $this->assignRoleToUser($userId, $singleAppRole);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email'    => $email,
                'password' => $password,
            ]);

        $result->assertStatus(200);
        $decoded = $this->decodeJwt(json_decode($result->getJSON(), true)['data']['access_token']);
        $scope = (array) $decoded->scope;

        $this->assertContains('single-app.only-perm', $scope);
        $this->assertNotContains(
            'other-app.only-perm',
            $scope,
            'resolveAll() must not leak a different application\'s permissions into an unrelated user\'s token'
        );
    }

    /**
     * Cache correctness at the HTTP boundary (critical requirement #2/#4):
     * granting a role's SECOND-app permission after the user has already
     * logged in once (warming the resolveAll() cache) must be visible on
     * the very next login — no stale cache leak through the real endpoint.
     */
    public function testPermissionChangeInSecondAppIsReflectedOnNextLoginNoStaleCache(): void
    {
        $appTwo = $this->insertApp('domain-app-cache');
        $role   = $this->insertRole('cache-cross-app-role');
        $permApp1 = $this->insertPerm(1, 'cache-cross-app.hub-only');
        $this->attachPermToRole($role, $permApp1);

        $email = 'cross-app-cache@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createActiveUser($email, $password);
        $this->assignRoleToUser($userId, $role);

        // First login: warms the resolveAll() cache with only the app-1 permission.
        $first = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $first->assertStatus(200);
        $firstScope = (array) $this->decodeJwt(json_decode($first->getJSON(), true)['data']['access_token'])->scope;
        $this->assertContains('cache-cross-app.hub-only', $firstScope);
        $this->assertNotContains('cache-cross-app.domain-only', $firstScope);

        // Grant a NEW permission on the second app to the same role, via the
        // real service path (so the production invalidateAll() call site in
        // RolePermissionAssignmentService::syncPermissions() is exercised,
        // not a manual test-only cache bust). The anti-escalation check
        // requires an actor context that already owns every permission being
        // granted, so we act as a superadmin (the actual role/permission
        // admin API is always called by a superadmin-scoped JWT in practice).
        $permApp2 = $this->insertPerm($appTwo, 'cache-cross-app.domain-only');
        $superadminActorContext = new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(
            999999,
            [],
            ['iam.superadmin-access']
        );
        Services::rolePermissionAssignmentService()->syncPermissions(
            $role,
            new \App\DTO\Request\Iam\AttachPermissionsRequestDTO(
                ['permission_ids' => [$permApp1, $permApp2]],
                Services::validation()
            ),
            $superadminActorContext
        );

        // Second login must reflect the new permission immediately.
        $second = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $second->assertStatus(200);
        $secondScope = (array) $this->decodeJwt(json_decode($second->getJSON(), true)['data']['access_token'])->scope;

        $this->assertContains('cache-cross-app.hub-only', $secondScope);
        $this->assertContains(
            'cache-cross-app.domain-only',
            $secondScope,
            'A permission granted on a second app after the resolveAll() cache was warmed must appear on the next login — no stale hit'
        );
    }

    public function testLoginJwtScopeForSuperadminIncludesPermissionsFromEveryRegisteredApplication(): void
    {
        $appTwo = $this->insertApp('domain-app-super');
        $this->insertPerm(1, 'super-scope.hub-perm');
        $this->insertPerm($appTwo, 'super-scope.domain-perm');

        $superRole = $this->testDb->table('roles')->where('code', 'superadmin')->get()->getRowArray();
        $this->assertNotNull($superRole, 'superadmin role must exist (RbacBootstrapSeeder)');

        $email = 'super-cross-app@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createActiveUser($email, $password);
        $this->assignRoleToUser($userId, (int) $superRole['id']);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['email' => $email, 'password' => $password]);

        $result->assertStatus(200);
        $decoded = $this->decodeJwt(json_decode($result->getJSON(), true)['data']['access_token']);
        $scope = (array) $decoded->scope;

        $this->assertContains('super-scope.hub-perm', $scope);
        $this->assertContains(
            'super-scope.domain-perm',
            $scope,
            'Superadmins must receive every permission from every registered application, not just app 1'
        );
    }

    private function createActiveUser(string $email, string $password): int
    {
        $userId = (int) $this->userModel->insert([
            'email'             => $email,
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'first_name'        => 'Test',
            'last_name'         => 'User',
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function insertApp(string $code): int
    {
        $this->testDb->table('applications')->insert([
            'code'       => $code,
            'name'       => ucfirst($code),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function insertPerm(int $appId, string $code): int
    {
        [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

        $this->testDb->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => "Test permission {$code}",
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function insertRole(string $code): int
    {
        $existing = $this->testDb->table('roles')->where('code', $code)->get()->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->testDb->table('roles')->insert([
            'code'               => $code,
            'name'               => $code,
            'description'        => "Test role {$code}",
            'is_system'          => 0,
            'is_self_assignable' => 0,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function attachPermToRole(int $roleId, int $permId): void
    {
        $exists = $this->testDb->table('role_permissions')
            ->where('role_id', $roleId)->where('permission_id', $permId)
            ->countAllResults() > 0;

        if (! $exists) {
            $this->testDb->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    private function assignRoleToUser(int $userId, int $roleId): void
    {
        $exists = $this->testDb->table('user_roles')
            ->where('user_id', $userId)->where('role_id', $roleId)
            ->countAllResults() > 0;

        if (! $exists) {
            $this->testDb->table('user_roles')->insert([
                'user_id'             => $userId,
                'role_id'             => $roleId,
                'assigned_at'         => date('Y-m-d H:i:s'),
                'assigned_by_user_id' => null,
            ]);
        }
    }

    private function decodeJwt(string $token): object
    {
        $secret = (string) config('Api')->jwtSecretKey;

        return JWT::decode($token, new Key($secret, 'HS256'));
    }
}
