<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\Scaffolding\StringHelper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regressions for the audit P0: StringHelper used to split every uppercase letter
 * with `(?<!^)[A-Z]`, producing `a_p_i_keys` for `APIKey`. This test pins the
 * acronym-aware behavior so a future "simplification" of the regex doesn't
 * silently re-introduce the bug.
 */
class StringHelperAcronymTest extends CIUnitTestCase
{
    /** @return iterable<string, array{0:string, 1:string, 2:string, 3:string}> */
    public static function acronymCases(): iterable
    {
        yield 'plain Studly' => ['Product', 'product', 'product', 'product'];
        yield 'compound Studly' => ['SchoolCategory', 'school_category', 'school-category', 'schoolCategory'];
        yield 'leading acronym' => ['APIKey', 'api_key', 'api-key', 'apiKey'];
        yield 'trailing acronym' => ['ParseXML', 'parse_xml', 'parse-xml', 'parseXml'];
        yield 'multi-acronym' => ['HTTPRequest', 'http_request', 'http-request', 'httpRequest'];
        yield 'acronym with digit' => ['OAuth2Token', 'o_auth2_token', 'o-auth2-token', 'oAuth2Token'];
        yield 'all caps' => ['XML', 'xml', 'xml', 'xml'];
        yield 'lowercase passthrough' => ['user', 'user', 'user', 'user'];
    }

    /** @dataProvider acronymCases */
    public function testTransformsPreserveAcronymsAsSingleWords(string $input, string $snake, string $kebab, string $camel): void
    {
        $this->assertSame($snake, StringHelper::toSnakeCase($input), "snake({$input})");
        $this->assertSame($kebab, StringHelper::toKebab($input), "kebab({$input})");
        $this->assertSame($camel, StringHelper::toCamelCase($input), "camel({$input})");
    }

    public function testHasAcronymRunDetectsConsecutiveCaps(): void
    {
        $this->assertTrue(StringHelper::hasAcronymRun('APIKey'));
        $this->assertTrue(StringHelper::hasAcronymRun('XML'));
        $this->assertTrue(StringHelper::hasAcronymRun('parseXML'));
        $this->assertFalse(StringHelper::hasAcronymRun('Product'));
        $this->assertFalse(StringHelper::hasAcronymRun('SchoolCategory'));
        $this->assertFalse(StringHelper::hasAcronymRun('user'));
    }

    public function testToSnakeCaseRegressionProducesNoSplitAcronymGarbage(): void
    {
        // Previously `(?<!^)[A-Z]` on `APIKey` produced `a_p_i_key`. Guard against
        // any regression that would re-introduce the pattern by checking for
        // consecutive single-letter underscored segments.
        foreach (['APIKey', 'HTTPRequest', 'XMLParser', 'OAuth2Token'] as $input) {
            $snake = StringHelper::toSnakeCase($input);
            $this->assertDoesNotMatchRegularExpression(
                '/(^|_)[a-z](_[a-z])+(_|$)/',
                $snake,
                "snake({$input}) = {$snake} contains split-acronym garbage"
            );
        }
    }
}
