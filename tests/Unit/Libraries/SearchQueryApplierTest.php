<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Query\SearchQueryApplier;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * SearchQueryApplier Tests
 */
class SearchQueryApplierTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserModel();

        // Ensure search is enabled
        putenv('SEARCH_ENABLED=true');
        putenv('SEARCH_MIN_LENGTH=3');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('SEARCH_ENABLED');
        putenv('SEARCH_MIN_LENGTH');
    }

    public function testApplyDoesNothingWhenQueryIsEmpty(): void
    {
        $builder = $this->model->builder();
        $sql1 = $builder->getCompiledSelect(false);

        SearchQueryApplier::apply($builder, '', ['email', 'first_name'], false);

        $sql2 = $builder->getCompiledSelect(false);

        $this->assertEquals($sql1, $sql2);
    }

    public function testApplyDoesNothingWhenSearchableFieldsEmpty(): void
    {
        $builder = $this->model->builder();
        $sql1 = $builder->getCompiledSelect(false);

        SearchQueryApplier::apply($builder, 'test', [], false);

        $sql2 = $builder->getCompiledSelect(false);

        $this->assertEquals($sql1, $sql2);
    }

    public function testApplyDoesNothingWhenQueryTooShort(): void
    {
        $builder = $this->model->builder();
        $sql1 = $builder->getCompiledSelect(false);

        SearchQueryApplier::apply($builder, 'ab', ['email', 'first_name'], false);

        $sql2 = $builder->getCompiledSelect(false);

        $this->assertEquals($sql1, $sql2);
    }

    public function testApplyDoesNothingWhenSearchDisabled(): void
    {
        putenv('SEARCH_ENABLED=false');

        $builder = $this->model->builder();
        $sql1 = $builder->getCompiledSelect(false);

        SearchQueryApplier::apply($builder, 'test', ['email', 'first_name'], false);

        $sql2 = $builder->getCompiledSelect(false);

        $this->assertEquals($sql1, $sql2);
    }

    public function testApplyLikeAddsLikeConditions(): void
    {
        $builder = $this->model->builder();

        SearchQueryApplier::applyLike($builder, 'john', ['email', 'first_name']);

        $sql = $builder->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('%john%', $sql);
    }

    public function testApplyLikeAddsOrLikeForMultipleFields(): void
    {
        $builder = $this->model->builder();

        SearchQueryApplier::applyLike($builder, 'test', ['email', 'first_name', 'last_name']);

        $sql = $builder->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testApplyLikeEscapesSpecialCharacters(): void
    {
        $builder = $this->model->builder();

        SearchQueryApplier::applyLike($builder, "test'OR'1'='1", ['email']);

        $sql = $builder->getCompiledSelect();

        // Query builder should escape the quote
        $this->assertStringNotContainsString("'OR'1'='1", $sql);
    }

    public function testApplyUsesLikeWhenFulltextDisabled(): void
    {
        $builder = $this->model->builder();

        SearchQueryApplier::apply($builder, 'test', ['email', 'first_name'], false);

        $sql = $builder->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringNotContainsString('MATCH', $sql);
    }

    public function testApplyWithModelInstance(): void
    {
        SearchQueryApplier::apply($this->model, 'john', ['email', 'first_name'], false);

        $sql = $this->model->builder()->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('%john%', $sql);
    }

    public function testApplyRespectsMinimumSearchLength(): void
    {
        putenv('SEARCH_MIN_LENGTH=5');

        $builder = $this->model->builder();
        $sql1 = $builder->getCompiledSelect(false);

        SearchQueryApplier::apply($builder, 'test', ['email'], false);

        $sql2 = $builder->getCompiledSelect(false);

        // Query should not be modified (too short)
        $this->assertEquals($sql1, $sql2);
    }

    public function testApplyWorksWithCustomMinLength(): void
    {
        putenv('SEARCH_MIN_LENGTH=2');

        $builder = $this->model->builder();

        SearchQueryApplier::apply($builder, 'ab', ['email'], false);

        $sql = $builder->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
    }
}
