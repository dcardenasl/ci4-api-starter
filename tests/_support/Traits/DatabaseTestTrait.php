<?php

namespace Tests\Support\Traits;

trait DatabaseTestTrait
{
    /**
     * Run migrations before tests
     */
    protected function runMigrations(): void
    {
        \Config\Database::forge()->dropDatabase('ci4_test');
        \Config\Database::forge()->createDatabase('ci4_test');

        $migrate = \Config\Services::migrations();
        $migrate->setNamespace(null)->latest();
    }

    /**
     * Seed the database for testing
     *
     * @param string|array $seeders
     */
    protected function seedDatabase($seeders = 'Tests\Support\Database\Seeds\TestUserSeeder'): void
    {
        if (is_string($seeders)) {
            $seeders = [$seeders];
        }

        foreach ($seeders as $seeder) {
            (new $seeder())->run();
        }
    }

    /**
     * Assert that a database table has a record matching attributes
     *
     * @param string $table
     * @param array $attributes
     */
    protected function assertDatabaseHas(string $table, array $attributes): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table($table);

        foreach ($attributes as $key => $value) {
            $builder->where($key, $value);
        }

        $count = $builder->countAllResults();

        $this->assertGreaterThan(
            0,
            $count,
            sprintf(
                'Failed asserting that table [%s] contains a row matching [%s]',
                $table,
                json_encode($attributes)
            )
        );
    }

    /**
     * Assert that a database table does not have a record matching attributes
     *
     * @param string $table
     * @param array $attributes
     */
    protected function assertDatabaseMissing(string $table, array $attributes): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table($table);

        foreach ($attributes as $key => $value) {
            $builder->where($key, $value);
        }

        $count = $builder->countAllResults();

        $this->assertEquals(
            0,
            $count,
            sprintf(
                'Failed asserting that table [%s] does not contain a row matching [%s]',
                $table,
                json_encode($attributes)
            )
        );
    }
}
