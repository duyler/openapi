<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\Internal\ArraySchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\CompositionSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ScalarSchemaKeywordParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[CoversClass(ScalarSchemaKeywordParser::class)]
#[CoversClass(ArraySchemaKeywordParser::class)]
#[CoversClass(ObjectSchemaKeywordParser::class)]
#[CoversClass(CompositionSchemaKeywordParser::class)]
final class SchemaKeywordParsersTest extends TestCase
{
    #[Test]
    public function scalar_parser_returns_scalar_keyword_args(): void
    {
        $parser = new ScalarSchemaKeywordParser('3.2.0');

        $args = $parser->build([
            'type' => 'string',
            'minLength' => 3,
            'maxLength' => 50,
            'default' => 'guest',
        ]);

        self::assertSame('string', $args['type']);
        self::assertSame(3, $args['minLength']);
        self::assertSame(50, $args['maxLength']);
        self::assertSame('guest', $args['default']);
        self::assertTrue($args['hasDefault']);
    }

    #[Test]
    public function scalar_parser_throws_on_zero_multiple_of(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('multipleOf MUST be strictly greater than 0');

        new ScalarSchemaKeywordParser()->build(['multipleOf' => 0]);
    }

    #[Test]
    public function scalar_parser_throws_on_negative_multiple_of(): void
    {
        $this->expectException(InvalidSchemaException::class);

        new ScalarSchemaKeywordParser()->build(['multipleOf' => -1.5]);
    }

    #[Test]
    public function scalar_parser_v3_2_extends_type_with_null_when_nullable(): void
    {
        $args = new ScalarSchemaKeywordParser('3.2.0')->build(['type' => 'string', 'nullable' => true]);

        self::assertSame(['string', 'null'], $args['type']);
    }

    #[Test]
    public function scalar_parser_v3_0_keeps_type_unchanged_when_nullable(): void
    {
        $args = new ScalarSchemaKeywordParser('3.0.0')->build(['type' => 'string', 'nullable' => true]);

        self::assertSame('string', $args['type']);
    }

    #[Test]
    public function scalar_parser_v3_0_exclusive_minimum_bool_uses_minimum(): void
    {
        $args = new ScalarSchemaKeywordParser('3.0.0')->build([
            'minimum' => 5,
            'exclusiveMinimum' => true,
        ]);

        self::assertSame(5.0, $args['exclusiveMinimum']);
    }

    #[Test]
    public function scalar_parser_v3_2_exclusive_minimum_takes_numeric_value(): void
    {
        $args = new ScalarSchemaKeywordParser('3.2.0')->build(['exclusiveMinimum' => 3]);

        self::assertSame(3.0, $args['exclusiveMinimum']);
    }

    #[Test]
    public function array_parser_returns_items_prefixItems_contains(): void
    {
        $parser = new ArraySchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            'items' => ['type' => 'string'],
            'prefixItems' => [['type' => 'integer']],
            'contains' => true,
            'minItems' => 1,
            'maxItems' => 5,
            'uniqueItems' => true,
            'unevaluatedItems' => false,
        ], $recurse);

        self::assertInstanceOf(Schema::class, $args['items']);
        self::assertIsArray($args['prefixItems']);
        self::assertTrue($args['contains']);
        self::assertSame(1, $args['minItems']);
        self::assertSame(5, $args['maxItems']);
        self::assertTrue($args['uniqueItems']);
        self::assertFalse($args['unevaluatedItems']);
    }

    #[Test]
    public function array_parser_throws_on_invalid_items_keyword(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "items"');

        new ArraySchemaKeywordParser()->build(['items' => 'not-a-schema'], static fn(): Schema => new Schema());
    }

    #[Test]
    public function object_parser_returns_properties_additional_required(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => is_array($data) ? new Schema(type: $data['type'] ?? null) : new Schema();

        $args = $parser->build([
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
            'additionalProperties' => false,
            'maxProperties' => 5,
            'patternProperties' => ['^x-' => ['type' => 'string']],
        ], $recurse);

        self::assertIsArray($args['properties']);
        self::assertArrayHasKey('name', $args['properties']);
        self::assertSame(['name'], $args['required']);
        self::assertFalse($args['additionalProperties']);
        self::assertSame(5, $args['maxProperties']);
        self::assertArrayHasKey('^x-', $args['patternProperties']);
    }

    #[Test]
    public function composition_parser_returns_ref_allOf_oneOf(): void
    {
        $parser = new CompositionSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            '$ref' => '#/components/schemas/User',
            'summary' => 'User ref',
            'description' => 'User description',
            'allOf' => [['type' => 'object']],
            'oneOf' => [['type' => 'string']],
            'not' => false,
            'if' => true,
        ], $recurse);

        self::assertSame('#/components/schemas/User', $args['ref']);
        self::assertSame('User ref', $args['refSummary']);
        self::assertSame('User description', $args['refDescription']);
        self::assertCount(1, $args['allOf']);
        self::assertCount(1, $args['oneOf']);
        self::assertFalse($args['not']);
        self::assertTrue($args['if']);
    }

    #[Test]
    public function composition_parser_returns_null_ref_summary_without_ref(): void
    {
        $args = new CompositionSchemaKeywordParser()->build(['summary' => 'standalone'], static fn(): Schema => new Schema());

        self::assertNull($args['ref']);
        self::assertNull($args['refSummary']);
    }

    #[Test]
    public function composition_parser_throws_on_invalid_not_keyword(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "not"');

        new CompositionSchemaKeywordParser()->build(['not' => 'invalid'], static fn(): Schema => new Schema());
    }
}
