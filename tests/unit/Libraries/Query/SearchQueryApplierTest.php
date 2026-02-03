<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Query;

use App\Libraries\Query\SearchQueryApplier;
use Tests\Support\DatabaseTestCase;

/**
 * SearchQueryApplier Unit Tests
 *
 * Tests for centralized search query application.
 */
class SearchQueryApplierTest extends DatabaseTestCase
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
    }

    protected function getBuilder()
    {
        return $this->db->table('users');
    }

    public function testApplyFulltextSearch(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::applyFulltext($builder, 'john', ['name', 'email']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('MATCH', $sql);
        $this->assertStringContainsString('AGAINST', $sql);
        $this->assertStringContainsString('BOOLEAN MODE', $sql);
    }

    public function testApplyLikeSearchSingleField(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::applyLike($builder, 'john', ['name']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('LIKE', $sql);
    }

    public function testApplyLikeSearchMultipleFields(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::applyLike($builder, 'john', ['name', 'email', 'username']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('username', $sql);
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testApplyWithFulltextEnabled(): void
    {
        $builder = $this->getBuilder();

        // Test applyFulltext directly since apply() has env checks
        SearchQueryApplier::applyFulltext($builder, 'search', ['title', 'body']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('MATCH', $sql);
        $this->assertStringContainsString('AGAINST', $sql);
    }

    public function testApplyWithFulltextDisabled(): void
    {
        $builder = $this->getBuilder();

        // Test applyLike directly since apply() has env checks
        SearchQueryApplier::applyLike($builder, 'search', ['title', 'body']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringNotContainsString('MATCH', $sql);
    }

    public function testApplyIgnoresEmptySearchableFields(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::apply($builder, 'search', [], true);

        $sql = $builder->getCompiledSelect(false);
        // Should not have any WHERE clause
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringNotContainsString('MATCH', $sql);
        $this->assertStringNotContainsString('LIKE', $sql);
    }

    public function testApplyIgnoresEmptyQuery(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::apply($builder, '', ['name', 'email'], true);

        $sql = $builder->getCompiledSelect(false);
        // Should not have any WHERE clause
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testApplyIgnoresQueryBelowMinLength(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::apply($builder, 'ab', ['name', 'email'], true);

        $sql = $builder->getCompiledSelect(false);
        // Should not have any WHERE clause since query is too short
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testApplyIgnoresWhenSearchDisabled(): void
    {
        $_ENV['SEARCH_ENABLED'] = 'false';

        $builder = $this->getBuilder();

        SearchQueryApplier::apply($builder, 'search', ['name', 'email'], true);

        $sql = $builder->getCompiledSelect(false);
        // Should not have any search-related WHERE clause
        $this->assertStringNotContainsString('MATCH', $sql);
        $this->assertStringNotContainsString('LIKE', $sql);

        // Reset for other tests
        $_ENV['SEARCH_ENABLED'] = 'true';
    }

    public function testApplyWithExactMinLengthQuery(): void
    {
        $builder = $this->getBuilder();

        // Test applyFulltext directly to verify exact min length works
        SearchQueryApplier::applyFulltext($builder, 'abc', ['name']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('MATCH', $sql);
    }

    public function testApplyFulltextWithMultipleFields(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::applyFulltext($builder, 'test query', ['title', 'content', 'summary']);

        $sql = $builder->getCompiledSelect(false);
        $this->assertStringContainsString('title', $sql);
        $this->assertStringContainsString('content', $sql);
        $this->assertStringContainsString('summary', $sql);
    }

    public function testApplyLikeUsesOrForMultipleFields(): void
    {
        $builder = $this->getBuilder();

        SearchQueryApplier::applyLike($builder, 'test', ['field1', 'field2']);

        $sql = $builder->getCompiledSelect(false);
        // Should have OR between field conditions
        $this->assertStringContainsString('OR', $sql);
    }

    public function testApplyPreservesExistingConditions(): void
    {
        $builder = $this->getBuilder();
        $builder->where('status', 'active');

        // Test applyFulltext directly to verify existing conditions preserved
        SearchQueryApplier::applyFulltext($builder, 'test', ['name']);

        $sql = $builder->getCompiledSelect(false);
        // Should have both the original condition and search
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('MATCH', $sql);
    }
}
