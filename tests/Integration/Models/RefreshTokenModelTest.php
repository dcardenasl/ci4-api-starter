<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * RefreshTokenModel Integration Tests
 */
class RefreshTokenModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected RefreshTokenModel $model;
    protected UserModel $userModel;
    protected int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RefreshTokenModel();
        $this->userModel = new UserModel();

        // Create test user
        $this->testUserId = $this->userModel->insert([
            'email' => 'test@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);
    }

    public function testInsertCreatesRefreshToken(): void
    {
        $data = [
            'user_id' => $this->testUserId,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ];

        $id = $this->model->insert($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindByTokenReturnsToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $result = $this->model->where('token', $token)->first();

        $this->assertNotNull($result);
        $this->assertEquals($this->testUserId, $result->user_id);
    }

    public function testDeleteByUserIdRemovesTokens(): void
    {
        // Insert tokens
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => 'token1',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => 'token2',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->where('user_id', $this->testUserId)->delete();

        $result = $this->model->where('user_id', $this->testUserId)->findAll();
        $this->assertEmpty($result);
    }
}
