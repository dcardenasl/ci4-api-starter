<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Shared base for all integration tests.
 *
 * Centralizing DatabaseTestTrait here causes PHP to share the static
 * $doneMigration property across every child class via inheritance, so
 * migrations run exactly once for the entire integration suite regardless
 * of how many test classes exist. Previously each class used the trait
 * directly, giving each its own $doneMigration → N full migrate cycles.
 */
abstract class IntegrationTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $basePath    = APPPATH . 'Database';
}
