<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Query;

use App\Libraries\Query\QueryBuilder;
use App\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * QueryBuilder Unit Tests
 *
 * Tests for the QueryBuilder class that provides fluent interface
 * for building complex database queries.
 */
class QueryBuilderTest extends DatabaseTestCase
{
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Set up environment variables for testing
        $_ENV['SEARCH_ENABLED'] = 'true';
        $_ENV['SEARCH_MIN_LENGTH'] = '3';
        $_ENV['PAGINATION_DEFAULT_LIMIT'] = '20';
        $_ENV['PAGINATION_MAX_LIMIT'] = '100';
    }

    protected function getModel(): UserModel
    {
        return new UserModel();
    }

    public function testFilterAppliesFiltersToModel(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->filter(['role' => 'admin']);

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testSortAppliesAscendingSort(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->sort('username');

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testSortAppliesDescendingSort(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->sort('-created_at');

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testSortAppliesMultipleSorts(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->sort('-created_at,username');

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testSearchReturnsBuilderInstance(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->search('test');

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testPaginateReturnsCorrectStructure(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(1, 20);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
    }

    public function testPaginateReturnsCorrectPageValue(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(3, 10);

        $this->assertEquals(3, $result['page']);
        $this->assertEquals(10, $result['perPage']);
    }

    public function testPaginateEnforcesMaxLimit(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(1, 200);

        // Should be capped at 100
        $this->assertEquals(100, $result['perPage']);
    }

    public function testPaginateUsesDefaultLimitForInvalidValue(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(1, 0);

        // Should use default of 20
        $this->assertEquals(20, $result['perPage']);
    }

    public function testPaginateEnforcesMinimumPage(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(-5, 20);

        $this->assertEquals(1, $result['page']);
    }

    public function testPaginateHandlesEmptyResults(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        // Filter for something that doesn't exist
        $builder->filter(['username' => 'nonexistent_user_xyz']);
        $result = $builder->paginate(1, 20);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['lastPage']);
        $this->assertEquals(0, $result['from']);
        $this->assertEquals(0, $result['to']);
    }

    public function testGetReturnsArray(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->get();

        $this->assertIsArray($result);
    }

    public function testFirstReturnsNullOrEntity(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        // Filter for something that doesn't exist
        $builder->filter(['username' => 'nonexistent_user_xyz']);
        $result = $builder->first();

        $this->assertNull($result);
    }

    public function testCountReturnsInteger(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->count();

        $this->assertIsInt($result);
    }

    public function testFluentInterface(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder
            ->filter(['role' => 'admin'])
            ->sort('-created_at')
            ->paginate(1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testFilterWithMultipleConditions(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->filter([
            'role' => 'admin',
        ])->paginate(1, 10);

        $this->assertIsArray($result);
    }

    public function testPaginateCalculatesLastPageCorrectly(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(1, 10);

        // lastPage should be at least 1
        $this->assertGreaterThanOrEqual(1, $result['lastPage']);

        // If there are items, verify lastPage calculation
        if ($result['total'] > 0) {
            $expectedLastPage = (int) ceil($result['total'] / $result['perPage']);
            $this->assertEquals($expectedLastPage, $result['lastPage']);
        }
    }

    public function testPaginateFromToCalculation(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->paginate(2, 5);

        // For page 2, limit 5: from should be 6, to should be min(10, total)
        if ($result['total'] > 5) {
            $this->assertEquals(6, $result['from']);
            $this->assertLessThanOrEqual($result['total'], $result['to']);
        } else {
            // When total is 0 or less than page 2 start, from and to will be 0
            $this->assertGreaterThanOrEqual(0, $result['from']);
        }
    }

    public function testFilterThenSort(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder
            ->filter(['role' => 'user'])
            ->sort('username,-created_at')
            ->get();

        $this->assertIsArray($result);
    }

    public function testSearchWithEmptyString(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $result = $builder->search('')->get();

        // Should still work, just return all results
        $this->assertIsArray($result);
    }

    public function testCountAfterFilter(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $builder->filter(['role' => 'nonexistent_role']);
        $count = $builder->count();

        $this->assertEquals(0, $count);
    }

    /**
     * SECURITY TEST: Verify protection against SQL injection via sort parameter
     */
    public function testSortRejectsInvalidFields(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $startTime = microtime(true);

        // Attempt SQL injection with SLEEP (should be rejected silently)
        $builder->sort('(SELECT SLEEP(5)),username');

        $result = $builder->paginate(1, 10);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete quickly (no SLEEP executed)
        // Allow up to 1 second for query execution (SLEEP would take 5+ seconds)
        $this->assertLessThan(1.0, $duration, 'Query took too long - possible SQL injection executed');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testSortWithWhitelistValidation(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        // Valid fields should work
        $builder->sort('username,-created_at');
        $result = $builder->paginate(1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testSortSkipsInvalidFieldsSilently(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        // Mix of valid and invalid fields
        $builder->sort('username,invalid_field_xyz,created_at');
        $result = $builder->get();

        // Should work, just omit invalid_field_xyz
        $this->assertIsArray($result);
    }

    public function testSortHandlesModelWithoutSortableFields(): void
    {
        // Create anonymous model without sortableFields property
        $model = new class () extends \CodeIgniter\Model {
            protected $table = 'test_table';
            protected $returnType = 'array';
            // Explicitly no sortableFields property

            public function findAll(?int $limit = null, int $offset = 0)
            {
                return [];
            }
        };

        $builder = new QueryBuilder($model);

        // Should not throw exception, just skip all sorts
        $builder->sort('any_field');
        $result = $builder->get();

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $this->assertIsArray($result);
    }

    public function testSortWithDescendingOrder(): void
    {
        $model = $this->getModel();

        // Seed at least one user for testing sort
        $model->insert([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('Test123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $builder = new QueryBuilder($model);

        // Test descending sort on valid field
        $builder->sort('-created_at');
        $result = $builder->get();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testSortWithMultipleSQLInjectionAttempts(): void
    {
        $model = $this->getModel();
        $builder = new QueryBuilder($model);

        $injectionAttempts = [
            '(SELECT * FROM users)',
            'username; DROP TABLE users--',
            'username UNION SELECT password FROM users',
            '1=1; UPDATE users SET role=\'admin\'',
            'username/**/OR/**/1=1',
        ];

        foreach ($injectionAttempts as $attempt) {
            $startTime = microtime(true);
            $builder->sort($attempt);
            $result = $builder->get();
            $duration = microtime(true) - $startTime;

            // Should complete quickly without executing injection
            $this->assertLessThan(1.0, $duration);
            $this->assertIsArray($result);
        }
    }
}
