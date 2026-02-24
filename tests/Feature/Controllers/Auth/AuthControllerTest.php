<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Interfaces\EmailServiceInterface;
use App\Interfaces\GoogleIdentityServiceInterface;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * AuthController Feature Tests
 *
 * Tests HTTP endpoints for authentication.
 * These tests verify the full request/response cycle.
 */
class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // Use app migrations

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();

        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('authService');
    }

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('authService');
        parent::tearDown();
    }

    // ==================== REGISTER ENDPOINT TESTS ====================

    public function testRegisterWithValidDataReturnsPendingApprovalMessage(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'new@example.com',
                'password' => 'ValidPass123!',
            ]);

        // Register returns 201 Created (not 200)
        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('user', $json['data']);
        $this->assertArrayNotHasKey('access_token', $json['data']);
        $this->assertArrayNotHasKey('refresh_token', $json['data']);
        $this->assertArrayHasKey('message', $json);
    }

    public function testRegisterWithInvalidDataReturnsValidationError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'invalid-email',
                'password' => 'weak',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
    }

    public function testRegisterWithDuplicateEmailReturnsError(): void
    {
        // Create existing user
        $this->userModel->insert([
            'email' => 'existing@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'existing@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testRegisterWithDuplicateEmailReturnsSpanishValidationMessage(): void
    {
        service('language')->setLocale('es');
        service('request')->setLocale('es');

        $this->userModel->insert([
            'email' => 'existing-es@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->withHeaders([
            'Accept-Language' => 'es',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'existing-es@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('email', $json['errors']);
        $this->assertEquals('Este email ya está registrado', $json['errors']['email']);
    }

    // ==================== LOGIN ENDPOINT TESTS ====================

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        // Create user
        $this->userModel->insert([
            'email' => 'login@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'login@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);
    }

    public function testLoginWithPendingApprovalReturnsForbidden(): void
    {
        $this->userModel->insert([
            'email' => 'pending@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'pending@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(403);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(403, $json['code']);
    }

    public function testLoginWithInvitedStatusReturnsForbidden(): void
    {
        $this->userModel->insert([
            'email' => 'invited@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'invited',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'invited@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(403);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(403, $json['code']);
    }

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'WrongPass123!',
            ]);

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    public function testLoginWithEmptyCredentialsReturnsError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => '',
                'password' => '',
            ]);

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    public function testGoogleLoginWithExistingActiveUserReturnsTokens(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'google-active@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-active',
            'email' => 'google-active@example.com',
            'first_name' => 'Google',
            'last_name' => 'User',
            'avatar_url' => 'https://example.com/avatar.png',
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);

        $updatedUser = $this->userModel->find($userId);
        $this->assertEquals('google', $updatedUser->oauth_provider);
        $this->assertEquals('google-sub-active', $updatedUser->oauth_provider_id);
        $this->assertNotNull($updatedUser->email_verified_at);
    }

    public function testGoogleLoginReturns403ForPendingApprovalUser(): void
    {
        $this->userModel->insert([
            'email' => 'google-pending@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-pending',
            'email' => 'google-pending@example.com',
            'first_name' => 'Pending',
            'last_name' => 'User',
            'avatar_url' => null,
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(403);
    }

    public function testGoogleLoginCreatesPendingUserAndReturns202WhenEmailDoesNotExist(): void
    {
        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-new',
            'email' => 'google-new@example.com',
            'first_name' => 'New',
            'last_name' => 'Google',
            'avatar_url' => 'https://example.com/new.png',
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(202);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayNotHasKey('access_token', $json['data']);
        $this->assertEquals('pending_approval', $json['data']['user']['status']);

        $created = $this->userModel->where('email', 'google-new@example.com')->first();
        $this->assertNotNull($created);
        $this->assertEquals('pending_approval', $created->status);
        $this->assertEquals('google', $created->oauth_provider);
        $this->assertEquals('google-sub-new', $created->oauth_provider_id);
        $this->assertNull($created->password);
    }

    public function testGoogleLoginConvertsInvitedUserToActiveAndReturnsTokens(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'google-invited@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'invited',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => 1,
            'invited_at' => date('Y-m-d H:i:s'),
            'invited_by' => 1,
            'email_verified_at' => null,
        ]);

        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-invited',
            'email' => 'google-invited@example.com',
            'first_name' => 'Invited',
            'last_name' => 'Google',
            'avatar_url' => null,
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('access_token', $json['data']);

        $updated = $this->userModel->find($userId);
        $this->assertEquals('active', $updated->status);
        $this->assertNull($updated->invited_at);
        $this->assertNull($updated->invited_by);
    }

    public function testGoogleLoginReactivatesSoftDeletedUserAsPendingApproval(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'google-deleted@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => 1,
        ]);

        $this->userModel->delete($userId);

        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-deleted',
            'email' => 'google-deleted@example.com',
            'first_name' => 'Deleted',
            'last_name' => 'Google',
            'avatar_url' => null,
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(202);

        $reactivated = $this->userModel->find($userId);
        $this->assertNotNull($reactivated);
        $this->assertEquals('pending_approval', $reactivated->status);
        $this->assertNull($reactivated->deleted_at);
        $this->assertNull($reactivated->approved_at);
        $this->assertEquals('google', $reactivated->oauth_provider);
        $this->assertEquals('google-sub-deleted', $reactivated->oauth_provider_id);
    }

    public function testGoogleLoginReturns409ForDifferentOauthProvider(): void
    {
        $this->userModel->insert([
            'email' => 'google-conflict@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'oauth_provider' => 'github',
            'oauth_provider_id' => 'github-sub-1',
        ]);

        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-conflict',
            'email' => 'google-conflict@example.com',
            'first_name' => 'Conflict',
            'last_name' => 'User',
            'avatar_url' => null,
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(409);
    }

    // ==================== PROTECTED ENDPOINT TESTS ====================

    public function testProtectedEndpointWithoutTokenReturnsUnauthorized(): void
    {
        $result = $this->get('/api/v1/users');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    public function testProtectedEndpointWithValidTokenReturnsData(): void
    {
        // Create user and get token
        $this->userModel->insert([
            'email' => 'auth@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'auth@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['data']['access_token'];

        // Access protected endpoint
        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/users');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testApprovePendingUserAllowsLogin(): void
    {
        $adminId = $this->userModel->insert([
            'email' => 'admin@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $pendingId = $this->userModel->insert([
            'email' => 'approve@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['data']['access_token'];

        $approveResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post("/api/v1/users/{$pendingId}/approve");

        $approveResult->assertStatus(200);

        $user = $this->userModel->find($pendingId);
        $this->assertEquals('active', $user->status);

        $loginPending = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'approve@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginPending->assertStatus(200);
    }

    // ==================== HEALTH CHECK TESTS ====================

    public function testHealthEndpointResponds(): void
    {
        $result = $this->get('/health');

        // Health returns 200 if all services OK, 503 if some fail
        // In test environment, some services may not be available
        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 503], true));
    }

    public function testHealthEndpointRespectsSpanishLocaleForCheckMessages(): void
    {
        service('language')->setLocale('es');
        service('request')->setLocale('es');

        $result = $this->withHeaders([
            'Accept-Language' => 'es',
        ])->get('/health');

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 503], true));

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('checks', $json);
        $this->assertArrayHasKey('database', $json['checks']);
        $this->assertArrayHasKey('message', $json['checks']['database']);

        $message = $json['checks']['database']['message'];
        $this->assertTrue(
            str_contains($message, 'Conexión a la base de datos exitosa')
            || str_contains($message, 'Error de conexión a la base de datos:')
        );
    }

    public function testPingEndpointReturnsOk(): void
    {
        $result = $this->get('/ping');

        $result->assertStatus(200);
    }

    /**
     * @param array<string, mixed> $identity
     */
    private function injectGoogleIdentityMock(array $identity): void
    {
        $googleIdentityService = $this->createMock(GoogleIdentityServiceInterface::class);
        $googleIdentityService
            ->method('verifyIdToken')
            ->willReturn($identity);

        \Config\Services::injectMock('googleIdentityService', $googleIdentityService);
        \Config\Services::resetSingle('authService');
    }

    private function injectEmailServiceMock(): void
    {
        $emailService = $this->createMock(EmailServiceInterface::class);
        $emailService
            ->method('queueTemplate')
            ->willReturn(1);

        \Config\Services::injectMock('emailService', $emailService);
        \Config\Services::resetSingle('authService');
    }
}
