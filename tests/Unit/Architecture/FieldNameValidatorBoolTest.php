<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\FieldNameValidator;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * Audit P2 — bool fields without an explicit modifier silently default to false.
 * Force the caller to pick one (required or nullable) so the API contract is
 * unambiguous to clients.
 */
class FieldNameValidatorBoolTest extends CIUnitTestCase
{
    public function testBoolWithoutExplicitModifierIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bool fields must be tagged/');
        (new FieldNameValidator())->validate([
            new Field(name: 'is_paid', type: 'bool', required: false, nullable: false),
        ]);
    }

    public function testBoolWithRequiredModifierIsAccepted(): void
    {
        $this->addToAssertionCount(1); // No exception expected
        (new FieldNameValidator())->validate([
            new Field(name: 'is_paid', type: 'bool', required: true, nullable: false),
        ]);
    }

    public function testBoolWithNullableModifierIsAccepted(): void
    {
        $this->addToAssertionCount(1); // No exception expected
        (new FieldNameValidator())->validate([
            new Field(name: 'is_paid', type: 'bool', required: false, nullable: true),
        ]);
    }
}
