<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Shared base for all integration tests.
 *
 * CI4's DatabaseTestTrait resets its private static $doneMigration via
 * #[AfterClass] after every test class, so migrateOnce=true still triggers
 * a full regress+migrate cycle before each class regardless of inheritance.
 * With 20+ integration test classes that DDL pressure is the root cause of
 * "MySQL server has gone away" errors in CI.
 *
 * Fix: $migrate=false tells CI4 to skip regress/migrate entirely (both
 * methods return early). We provide equivalent isolation by truncating all
 * non-migration tables in setUpBeforeClass() — a TRUNCATE is orders of
 * magnitude faster than DROP+CREATE for 25+ tables. Seeds still run per
 * test method via setUpSeed() because seedOnce defaults to false.
 */
abstract class IntegrationTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = false;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';
    protected $basePath    = APPPATH . 'Database';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::truncateAllTables();
    }

    protected static function truncateAllTables(): void
    {
        $db = Database::connect('tests');
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->listTables() as $table) {
            if ($table !== 'migrations') {
                $db->table($table)->truncate();
            }
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}
