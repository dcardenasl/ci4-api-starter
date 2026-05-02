<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\Scaffolding\FieldStringParser;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pin the contract for FK referential action overrides (audit P2). The syntax
 * `fk:table:setnull|filterable` allows callers to escape the default CASCADE.
 */
class FieldStringParserModifiersTest extends CIUnitTestCase
{
    public function testFkDefaultsToCascade(): void
    {
        $fields = (new FieldStringParser())->parse('user_id:fk:users:required');
        $this->assertCount(1, $fields);
        $this->assertSame('CASCADE', $fields[0]->fkOnDelete);
    }

    public function testFkRestrictModifierSetsRestrict(): void
    {
        $fields = (new FieldStringParser())->parse('user_id:fk:users:required|restrict');
        $this->assertSame('RESTRICT', $fields[0]->fkOnDelete);
    }

    public function testFkSetNullModifierSetsSetNull(): void
    {
        $fields = (new FieldStringParser())->parse('user_id:fk:users:nullable|setnull');
        $this->assertSame('SET NULL', $fields[0]->fkOnDelete);
    }

    public function testNonFkFieldsIgnoreReferentialModifiers(): void
    {
        $fields = (new FieldStringParser())->parse('name:string:required|restrict');
        $this->assertSame('CASCADE', $fields[0]->fkOnDelete, 'Non-FK fields should not honor restrict/setnull');
    }
}
