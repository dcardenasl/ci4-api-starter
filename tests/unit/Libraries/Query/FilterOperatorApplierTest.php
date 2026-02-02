<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Query;

use App\Libraries\Query\FilterOperatorApplier;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * FilterOperatorApplier Unit Tests
 *
 * Tests for centralized filter operator application.
 * Uses database test traits to test with actual query builder.
 */
class FilterOperatorApplierTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getBuilder()
    {
        return $this->db->table('users');
    }

    public function testApplyEqualsOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'status', '=', 'active');

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('`status`', $sql);
        $this->assertStringContainsString('active', $sql);
    }

    public function testApplyNotEqualsOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'status', '!=', 'deleted');

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('!=', $sql);
    }

    public function testApplyGreaterThanOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'age', '>', 18);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('age', $sql);
        $this->assertStringContainsString('>', $sql);
    }

    public function testApplyLessThanOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'price', '<', 100);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('price', $sql);
        $this->assertStringContainsString('<', $sql);
    }

    public function testApplyGreaterThanOrEqualsOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'quantity', '>=', 5);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('quantity', $sql);
        $this->assertStringContainsString('>=', $sql);
    }

    public function testApplyLessThanOrEqualsOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'discount', '<=', 50);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('discount', $sql);
        $this->assertStringContainsString('<=', $sql);
    }

    public function testApplyLikeOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'name', 'LIKE', 'john');

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('LIKE', $sql);
    }

    public function testApplyInOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'status', 'IN', ['active', 'pending']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('IN', $sql);
    }

    public function testApplyNotInOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'category', 'NOT IN', ['archived', 'deleted']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('category', $sql);
        $this->assertStringContainsString('NOT IN', $sql);
    }

    public function testApplyBetweenOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'price', 'BETWEEN', [10, 100]);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('price', $sql);
        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('<=', $sql);
    }

    public function testApplyBetweenOperatorIgnoresInvalidValue(): void
    {
        $builder = $this->getBuilder();

        // Get SQL before applying filter
        $sqlBefore = $builder->getCompiledSelect(false);

        // Reset builder and apply invalid BETWEEN
        $builder = $this->getBuilder();
        FilterOperatorApplier::apply($builder, 'price', 'BETWEEN', [10]); // Only one value

        $sqlAfter = $builder->getCompiledSelect(false);

        // SQL should be equivalent (no WHERE clause added)
        $this->assertStringNotContainsString('price', $sqlAfter);
    }

    public function testApplyBetweenOperatorIgnoresNonArrayValue(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'price', 'BETWEEN', 'invalid');

        $sql = $builder->getCompiledSelect(false);
        // Should not have any price filter since value is invalid
        $this->assertStringNotContainsString('price', $sql);
    }

    public function testApplyIsNullOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'deleted_at', 'IS NULL', null);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('deleted_at', $sql);
        $this->assertStringContainsString('IS NULL', $sql);
    }

    public function testApplyIsNotNullOperator(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'email_verified_at', 'IS NOT NULL', null);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('email_verified_at', $sql);
        $this->assertStringContainsString('IS NOT NULL', $sql);
    }

    public function testApplyUnknownOperatorDoesNothing(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'field', 'UNKNOWN', 'value');

        $sql = $builder->getCompiledSelect(false);
        // Unknown operator should not add any WHERE clause
        $this->assertStringNotContainsString('field', $sql);
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testApplyWithNumericValues(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'id', '=', 42);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('42', $sql);
    }

    public function testApplyMultipleOperators(): void
    {
        $builder = $this->getBuilder();

        FilterOperatorApplier::apply($builder, 'status', '=', 'active');
        FilterOperatorApplier::apply($builder, 'role', '!=', 'guest');
        FilterOperatorApplier::apply($builder, 'age', '>=', 18);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('role', $sql);
        $this->assertStringContainsString('age', $sql);
    }
}
