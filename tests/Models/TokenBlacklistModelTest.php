<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\TokenBlacklistModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * TokenBlacklistModel Integration Tests
 *
 * Tests database operations for token blacklist including
 * adding tokens, checking blacklist status, and cleanup.
 */
class TokenBlacklistModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected TokenBlacklistModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TokenBlacklistModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Insert test blacklist entries
        $this->db->table('token_blacklist')->insertBatch([
            [
                'token_jti' => 'valid_blacklisted_jti',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour from now
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'token_jti' => 'expired_blacklisted_jti',
                'expires_at' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ],
            [
                'token_jti' => 'long_lived_jti',
                'expires_at' => date('Y-m-d H:i:s', time() + 86400), // 1 day from now
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRequiresTokenJti(): void
    {
        $data = [
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testValidationRequiresExpiresAt(): void
    {
        $data = [
            'token_jti' => 'test_jti_' . uniqid(),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testValidationRequiresUniqueJti(): void
    {
        $data = [
            'token_jti' => 'valid_blacklisted_jti', // Duplicate from seed
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('token_jti', $errors);
    }

    public function testInsertValidBlacklistEntry(): void
    {
        $data = [
            'token_jti' => 'new_unique_jti_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testJtiCanBeMaxLength(): void
    {
        $longJti = str_repeat('a', 255);

        $data = [
            'token_jti' => $longJti,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    // ==================== IS BLACKLISTED TESTS ====================

    public function testIsBlacklistedReturnsTrueForBlacklistedToken(): void
    {
        $result = $this->model->isBlacklisted('valid_blacklisted_jti');

        $this->assertTrue($result);
    }

    public function testIsBlacklistedReturnsFalseForNonExistentToken(): void
    {
        $result = $this->model->isBlacklisted('non_existent_jti');

        $this->assertFalse($result);
    }

    public function testIsBlacklistedReturnsFalseForExpiredBlacklistEntry(): void
    {
        // This JTI exists but is expired
        $result = $this->model->isBlacklisted('expired_blacklisted_jti');

        $this->assertFalse($result);
    }

    public function testIsBlacklistedIsCaseSensitive(): void
    {
        $result1 = $this->model->isBlacklisted('valid_blacklisted_jti');
        $result2 = $this->model->isBlacklisted('VALID_BLACKLISTED_JTI');

        $this->assertTrue($result1);
        $this->assertFalse($result2);
    }

    public function testIsBlacklistedHandlesEmptyString(): void
    {
        $result = $this->model->isBlacklisted('');

        $this->assertFalse($result);
    }

    public function testIsBlacklistedHandlesSpecialCharacters(): void
    {
        // Add entry with special characters
        $specialJti = 'jti-with-!@#$%^&*()-chars';
        $this->model->insert([
            'token_jti' => $specialJti,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->model->isBlacklisted($specialJti);

        $this->assertTrue($result);
    }

    // ==================== ADD TO BLACKLIST TESTS ====================

    public function testAddToBlacklistCreatesEntry(): void
    {
        $jti = 'new_blacklisted_' . uniqid();
        $expiresAt = time() + 3600;

        $result = $this->model->addToBlacklist($jti, $expiresAt);

        $this->assertTrue($result);

        // Verify in database
        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record);
    }

    public function testAddToBlacklistSetsCorrectExpiration(): void
    {
        $jti = 'expiry_test_' . uniqid();
        $expiresAt = time() + 7200; // 2 hours

        $this->model->addToBlacklist($jti, $expiresAt);

        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $recordExpiresAt = strtotime($record->expires_at);
        $this->assertEquals($expiresAt, $recordExpiresAt, '', 2); // 2 second tolerance
    }

    public function testAddToBlacklistSetsCreatedAt(): void
    {
        $jti = 'created_test_' . uniqid();
        $expiresAt = time() + 3600;

        $beforeTime = time();
        $this->model->addToBlacklist($jti, $expiresAt);
        $afterTime = time();

        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $createdAt = strtotime($record->created_at);
        $this->assertGreaterThanOrEqual($beforeTime, $createdAt);
        $this->assertLessThanOrEqual($afterTime, $createdAt);
    }

    public function testAddToBlacklistReturnsFalseForDuplicateJti(): void
    {
        $jti = 'duplicate_test_' . uniqid();
        $expiresAt = time() + 3600;

        // Add first time - should succeed
        $result1 = $this->model->addToBlacklist($jti, $expiresAt);
        $this->assertTrue($result1);

        // Add second time - should fail
        $result2 = $this->model->addToBlacklist($jti, $expiresAt);
        $this->assertFalse($result2);
    }

    public function testAddToBlacklistHandlesPastExpiration(): void
    {
        $jti = 'past_expiry_' . uniqid();
        $pastExpiresAt = time() - 3600; // Already expired

        $result = $this->model->addToBlacklist($jti, $pastExpiresAt);

        // Should still succeed - cleanup happens separately
        $this->assertTrue($result);

        // But isBlacklisted should return false since it's expired
        $isBlacklisted = $this->model->isBlacklisted($jti);
        $this->assertFalse($isBlacklisted);
    }

    public function testAddToBlacklistHandlesFarFutureExpiration(): void
    {
        $jti = 'far_future_' . uniqid();
        $farFutureExpiresAt = time() + (86400 * 365); // 1 year

        $result = $this->model->addToBlacklist($jti, $farFutureExpiresAt);

        $this->assertTrue($result);
        $this->assertTrue($this->model->isBlacklisted($jti));
    }

    // ==================== DELETE EXPIRED TESTS ====================

    public function testDeleteExpiredRemovesOnlyExpiredEntries(): void
    {
        $deletedCount = $this->model->deleteExpired();

        // We seeded 1 expired entry
        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify expired entry is gone
        $expiredRecord = $this->db->table('token_blacklist')
            ->where('token_jti', 'expired_blacklisted_jti')
            ->get()
            ->getFirstRow();

        $this->assertNull($expiredRecord);

        // Verify valid entries still exist
        $validRecord = $this->db->table('token_blacklist')
            ->where('token_jti', 'valid_blacklisted_jti')
            ->get()
            ->getFirstRow();

        $this->assertNotNull($validRecord);
    }

    public function testDeleteExpiredReturnsZeroWhenNothingExpired(): void
    {
        // First delete all expired
        $this->model->deleteExpired();

        // Try again - should be 0
        $deletedCount = $this->model->deleteExpired();

        $this->assertEquals(0, $deletedCount);
    }

    public function testDeleteExpiredHandlesMultipleExpiredEntries(): void
    {
        // Add multiple expired entries
        for ($i = 0; $i < 5; $i++) {
            $this->db->table('token_blacklist')->insert([
                'token_jti' => 'expired_batch_' . $i,
                'expires_at' => date('Y-m-d H:i:s', time() - 3600 - $i),
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ]);
        }

        $deletedCount = $this->model->deleteExpired();

        // Should delete at least the 5 we just added + 1 from seed
        $this->assertGreaterThanOrEqual(6, $deletedCount);
    }

    public function testDeleteExpiredAtBoundary(): void
    {
        // Add entry expiring in 1 second
        $jti = 'boundary_test_' . uniqid();
        $this->db->table('token_blacklist')->insert([
            'token_jti' => $jti,
            'expires_at' => date('Y-m-d H:i:s', time() + 1),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Should not be deleted yet
        $count1 = $this->model->deleteExpired();
        $record1 = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();
        $this->assertNotNull($record1);

        // Wait 2 seconds
        sleep(2);

        // Should now be deleted
        $count2 = $this->model->deleteExpired();
        $record2 = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();
        $this->assertNull($record2);
    }

    // ==================== EDGE CASES ====================

    public function testMultipleBlacklistEntriesCanExist(): void
    {
        $entries = [];
        for ($i = 0; $i < 10; $i++) {
            $jti = 'batch_jti_' . $i . '_' . uniqid();
            $entries[] = [
                'token_jti' => $jti,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        $result = $this->db->table('token_blacklist')->insertBatch($entries);

        $this->assertEquals(10, $result);

        // All should be blacklisted
        foreach ($entries as $entry) {
            $isBlacklisted = $this->model->isBlacklisted($entry['token_jti']);
            $this->assertTrue($isBlacklisted);
        }
    }

    public function testBlacklistWorksWithVeryLongJti(): void
    {
        $veryLongJti = str_repeat('x', 255);

        $result = $this->model->addToBlacklist($veryLongJti, time() + 3600);

        $this->assertTrue($result);
        $this->assertTrue($this->model->isBlacklisted($veryLongJti));
    }

    public function testBlacklistWorksWithNumericJti(): void
    {
        $numericJti = '1234567890';

        $result = $this->model->addToBlacklist($numericJti, time() + 3600);

        $this->assertTrue($result);
        $this->assertTrue($this->model->isBlacklisted($numericJti));
    }

    public function testBlacklistWorksWithUuidJti(): void
    {
        $uuidJti = 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f56789';

        $result = $this->model->addToBlacklist($uuidJti, time() + 3600);

        $this->assertTrue($result);
        $this->assertTrue($this->model->isBlacklisted($uuidJti));
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function testCreatedAtIsAutomaticallySet(): void
    {
        $jti = 'auto_created_' . uniqid();

        $this->model->addToBlacklist($jti, time() + 3600);

        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record->created_at);
        $this->assertNotEmpty($record->created_at);
    }

    public function testExpiresAtIsStoredCorrectly(): void
    {
        $jti = 'expiry_check_' . uniqid();
        $expiresAt = time() + 5400; // 1.5 hours

        $this->model->addToBlacklist($jti, $expiresAt);

        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $storedExpiresAt = strtotime($record->expires_at);
        $this->assertEquals($expiresAt, $storedExpiresAt, '', 2);
    }

    public function testAutoIncrementIdWorks(): void
    {
        $jti1 = 'id_test_1_' . uniqid();
        $jti2 = 'id_test_2_' . uniqid();

        $id1 = $this->model->addToBlacklist($jti1, time() + 3600);
        $id2 = $this->model->addToBlacklist($jti2, time() + 3600);

        $record1 = $this->db->table('token_blacklist')
            ->where('token_jti', $jti1)
            ->get()
            ->getFirstRow();

        $record2 = $this->db->table('token_blacklist')
            ->where('token_jti', $jti2)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record1->id);
        $this->assertNotNull($record2->id);
        $this->assertNotEquals($record1->id, $record2->id);
    }
}
