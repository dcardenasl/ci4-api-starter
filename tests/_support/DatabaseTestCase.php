<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Base test case for database tests with foreign key constraint handling
 *
 * This class extends CIUnitTestCase and overrides the database refresh
 * behavior to temporarily disable foreign key checks, preventing
 * constraint errors when truncating tables during test setup/cleanup.
 */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Load the database and disable foreign key checks if refresh is enabled
     */
    protected function setUp(): void
    {
        parent::setUp();

        // If refresh is enabled and database is available, disable foreign key checks
        if ($this->refresh === true && isset($this->db) && $this->db !== null) {
            $driver = $this->db->DBDriver;

            // Only disable foreign key checks for MySQL/MySQLi
            if (in_array($driver, ['MySQLi', 'MySQL'], true)) {
                $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
            }
        }
    }

    /**
     * Re-enable foreign key checks after test completes
     */
    protected function tearDown(): void
    {
        // Re-enable foreign key checks if they were disabled
        if ($this->refresh === true && isset($this->db) && $this->db !== null) {
            $driver = $this->db->DBDriver;

            // Only re-enable for MySQL/MySQLi
            if (in_array($driver, ['MySQLi', 'MySQL'], true)) {
                $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        parent::tearDown();
    }
}
