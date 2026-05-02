<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Scaffolding;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\FieldNameValidator;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

final class FieldNameValidatorTest extends CIUnitTestCase
{
    private FieldNameValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FieldNameValidator();
    }

    public function testAcceptsValidFieldNames(): void
    {
        $this->validator->validate([
            new Field(name: 'name', type: 'string'),
            new Field(name: 'price', type: 'decimal'),
            new Field(name: 'category_id', type: 'fk', fkTable: 'categories'),
            new Field(name: 'is_active', type: 'bool'),
        ]);

        $this->assertTrue(true); // No exception = pass
    }

    public function testRejectsPhpReservedKeyword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'class' is a PHP reserved keyword");

        $this->validator->validate([
            new Field(name: 'class', type: 'string'),
        ]);
    }

    public function testRejectsEngineManagedColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("engine-managed column");

        $this->validator->validate([
            new Field(name: 'created_at', type: 'datetime'),
        ]);
    }

    public function testRejectsDuplicateFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate field');

        $this->validator->validate([
            new Field(name: 'name', type: 'string'),
            new Field(name: 'name', type: 'int'),
        ]);
    }

    public function testRejectsMysqlReservedWord(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MySQL reserved word');

        $this->validator->validate([
            new Field(name: 'order', type: 'int'),
        ]);
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid identifier');

        $this->validator->validate([
            new Field(name: '2invalid', type: 'string'),
        ]);
    }

    public function testRejectsIdColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'id' collides");

        $this->validator->validate([
            new Field(name: 'id', type: 'int'),
        ]);
    }

    public function testCaseInsensitiveReservedDetection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate([
            new Field(name: 'Class', type: 'string'),
        ]);
    }
}
