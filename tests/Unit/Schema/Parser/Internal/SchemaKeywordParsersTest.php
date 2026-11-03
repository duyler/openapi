<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Exception\TypeArrayNotSupportedException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\Internal\ArraySchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\CompositionSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ResourceSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ScalarSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\SchemaKeywordAccumulator;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

use function is_array;
use function sprintf;
use function str_contains;

#[CoversClass(ScalarSchemaKeywordParser::class)]
#[CoversClass(ArraySchemaKeywordParser::class)]
#[CoversClass(ObjectSchemaKeywordParser::class)]
#[CoversClass(CompositionSchemaKeywordParser::class)]
#[CoversClass(ResourceSchemaKeywordParser::class)]
#[CoversClass(SchemaKeywordAccumulator::class)]
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
    public function scalar_parser_openapi_30_profile_rejects_type_array(): void
    {
        $parser = new ScalarSchemaKeywordParser('3.0.0', dialectProfile: DialectProfile::forOpenApi30());

        $this->expectException(TypeArrayNotSupportedException::class);
        $this->expectExceptionMessage('Type arrays are not allowed in OpenAPI 3.0 documents');

        $parser->build(['type' => ['string', 'null']]);
    }

    #[Test]
    public function scalar_parser_openapi_30_reject_message_does_not_expose_spec_values(): void
    {
        $parser = new ScalarSchemaKeywordParser('3.0.0', dialectProfile: DialectProfile::forOpenApi30());

        try {
            $parser->build(['type' => ['string', 'null']]);
            self::fail(TypeArrayNotSupportedException::class . ' was not thrown');
        } catch (TypeArrayNotSupportedException $exception) {
            $message = $exception->getMessage();

            self::assertTrue(str_contains($message, 'Type arrays are not allowed'));
            self::assertFalse(str_contains($message, "'string'"));
            self::assertFalse(str_contains($message, "'null'"));
            self::assertFalse(str_contains($message, '['));
        }
    }

    #[Test]
    public function scalar_parser_openapi_31_profile_keeps_type_array(): void
    {
        $args = new ScalarSchemaKeywordParser('3.1.0')->build(['type' => ['string', 'null']]);

        self::assertSame(['string', 'null'], $args['type']);
    }

    #[Test]
    public function scalar_parser_openapi_30_profile_keeps_empty_type_array(): void
    {
        $parser = new ScalarSchemaKeywordParser('3.0.0', dialectProfile: DialectProfile::forOpenApi30());

        $args = $parser->build(['type' => []]);

        self::assertSame([], $args['type']);
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
    public function array_parser_returns_items_prefix_items_contains(): void
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
    public function composition_parser_returns_ref_all_of_one_of(): void
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
    public function composition_parser_builds_discriminator_with_mapping(): void
    {
        $parser = new CompositionSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            'discriminator' => [
                'propertyName' => 'petType',
                'mapping' => [
                    'dog' => '#/components/schemas/Dog',
                    'cat' => '#/components/schemas/Cat',
                ],
                'defaultMapping' => 'dog',
            ],
        ], $recurse);

        $discriminator = $args['discriminator'];

        self::assertInstanceOf(Discriminator::class, $discriminator);
        self::assertSame('petType', $discriminator->propertyName);
        self::assertSame('#/components/schemas/Dog', $discriminator->mapping['dog']);
        self::assertSame('#/components/schemas/Cat', $discriminator->mapping['cat']);
        self::assertSame('dog', $discriminator->defaultMapping);
    }

    #[Test]
    public function composition_parser_builds_discriminator_with_empty_array(): void
    {
        $args = new CompositionSchemaKeywordParser()->build(['discriminator' => []], static fn(): Schema => new Schema());

        $discriminator = $args['discriminator'];

        self::assertNull($discriminator->propertyName);
        self::assertNull($discriminator->mapping);
        self::assertNull($discriminator->defaultMapping);
    }

    #[Test]
    public function composition_parser_throws_on_invalid_not_keyword(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "not"');

        new CompositionSchemaKeywordParser()->build(['not' => 'invalid'], static fn(): Schema => new Schema());
    }

    #[Test]
    public function array_parser_keyword_accepts_schema_bool_and_null(): void
    {
        $parser = new ArraySchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            'items' => ['a' => 1],
            'contains' => true,
            'unevaluatedItems' => false,
        ], $recurse);

        self::assertInstanceOf(Schema::class, $args['items']);
        self::assertTrue($args['contains']);
        self::assertFalse($args['unevaluatedItems']);
        self::assertNull($parser->build(['items' => null], $recurse)['items']);
    }

    #[Test]
    #[DataProvider('garbage_keyword_value_provider')]
    public function array_parser_throws_on_garbage_keyword_value(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "items", got ' . $type);

        new ArraySchemaKeywordParser()->build(['items' => $value], static fn(): Schema => new Schema());
    }

    #[Test]
    #[DataProvider('garbage_keyword_value_provider')]
    public function composition_parser_throws_on_garbage_keyword_value(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "not", got ' . $type);

        new CompositionSchemaKeywordParser()->build(['not' => $value], static fn(): Schema => new Schema());
    }

    #[Test]
    public function array_parser_schema_list_builds_schemas_from_bool_and_array_elements(): void
    {
        $args = new ArraySchemaKeywordParser()->build(
            ['prefixItems' => [['type' => 'integer'], false]],
            static fn(bool|array $data): Schema => new Schema(type: 'nested'),
        );

        self::assertCount(2, $args['prefixItems']);
        self::assertInstanceOf(Schema::class, $args['prefixItems'][0]);
        self::assertInstanceOf(Schema::class, $args['prefixItems'][1]);
    }

    #[Test]
    public function array_parser_schema_list_throws_on_garbage_element(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "prefixItems", got int');

        new ArraySchemaKeywordParser()->build(
            ['prefixItems' => [['type' => 'string'], 42]],
            static fn(): Schema => new Schema(),
        );
    }

    #[Test]
    public function array_parser_schema_list_throws_invalid_schema_exception_on_non_array_value(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array for schema keyword "prefixItems", got int');

        new ArraySchemaKeywordParser()->build(
            ['prefixItems' => 42],
            static fn(): Schema => new Schema(),
        );
    }

    #[Test]
    public function composition_parser_schema_list_throws_on_garbage_element(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "allOf", got int');

        new CompositionSchemaKeywordParser()->build(
            ['allOf' => [['type' => 'string'], 42]],
            static fn(): Schema => new Schema(),
        );
    }

    #[Test]
    #[DataProvider('garbage_keyword_value_provider')]
    public function object_parser_throws_on_garbage_property_names(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "propertyNames", got ' . $type);

        new ObjectSchemaKeywordParser()->build(['propertyNames' => $value], static fn(): Schema => new Schema());
    }

    #[Test]
    #[DataProvider('garbage_optional_schema_keyword_provider')]
    public function object_parser_throws_on_garbage_optional_schema_keyword(string $key, mixed $value): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "' . $key . '", got');

        new ObjectSchemaKeywordParser()->build([$key => $value], static fn(): Schema => new Schema());
    }

    #[Test]
    public function object_parser_optional_schema_accepts_schema_bool_null(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            'additionalProperties' => ['a' => 1],
            'unevaluatedProperties' => true,
            'contentSchema' => false,
        ], $recurse);

        self::assertInstanceOf(Schema::class, $args['additionalProperties']);
        self::assertTrue($args['unevaluatedProperties']);
        self::assertFalse($args['contentSchema']);
        self::assertNull($parser->build(['contentSchema' => null], $recurse)['contentSchema']);
    }

    #[Test]
    public function resource_parser_returns_defs_map_with_bool_and_array_entries(): void
    {
        $parser = new ResourceSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build([
            '$defs' => [
                'objectSchema' => ['type' => 'string'],
                'trueSchema' => true,
                'falseSchema' => false,
            ],
        ], $recurse);

        self::assertSame(['objectSchema', 'trueSchema', 'falseSchema'], array_keys($args['defs']));
        self::assertInstanceOf(Schema::class, $args['defs']['objectSchema']);
        self::assertInstanceOf(Schema::class, $args['defs']['trueSchema']);
        self::assertInstanceOf(Schema::class, $args['defs']['falseSchema']);
    }

    #[Test]
    public function resource_parser_returns_null_defs_when_keyword_absent(): void
    {
        $args = new ResourceSchemaKeywordParser()->build([], static fn(): Schema => new Schema());

        self::assertNull($args['defs']);
    }

    #[Test]
    public function resource_parser_returns_null_defs_for_null_value(): void
    {
        $args = new ResourceSchemaKeywordParser()->build(['$defs' => null], static fn(): Schema => new Schema());

        self::assertNull($args['defs']);
    }

    #[Test]
    public function resource_parser_throws_on_garbage_defs_element(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "$defs", got int');

        new ResourceSchemaKeywordParser()->build(
            ['$defs' => ['a' => ['type' => 'string'], 'b' => 42]],
            static fn(): Schema => new Schema(),
        );
    }

    #[Test]
    #[DataProvider('garbage_keyword_value_provider')]
    public function resource_parser_throws_on_non_array_defs_value(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array for schema keyword "$defs", got ' . $type);

        new ResourceSchemaKeywordParser()->build(['$defs' => $value], static fn(): Schema => new Schema());
    }

    #[Test]
    public function scalar_parser_collects_resource_identification_keywords(): void
    {
        $args = new ScalarSchemaKeywordParser()->build([
            '$id' => 'https://example.com/root',
            '$anchor' => 'nameAnchor',
            '$dynamicAnchor' => 'node',
            '$dynamicRef' => '#node',
            '$comment' => 'resource comment',
            '$vocabulary' => [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
        ]);

        self::assertSame('https://example.com/root', $args['id']);
        self::assertSame('nameAnchor', $args['anchor']);
        self::assertSame('node', $args['dynamicAnchor']);
        self::assertSame('#node', $args['dynamicRef']);
        self::assertSame('resource comment', $args['comment']);
        self::assertSame(
            [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
            $args['vocabulary'],
        );
    }

    #[Test]
    public function scalar_parser_defaults_resource_keywords_to_null(): void
    {
        $args = new ScalarSchemaKeywordParser()->build([]);

        self::assertNull($args['id']);
        self::assertNull($args['anchor']);
        self::assertNull($args['dynamicAnchor']);
        self::assertNull($args['dynamicRef']);
        self::assertNull($args['comment']);
        self::assertNull($args['vocabulary']);
    }

    #[Test]
    public function object_parser_builds_dependent_required_map(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build([
            'dependentRequired' => [
                'creditCard' => ['billingAddress'],
                'coupon' => ['discount'],
            ],
        ], $recurse);

        self::assertSame([
            'creditCard' => ['billingAddress'],
            'coupon' => ['discount'],
        ], $args['dependentRequired']);
    }

    #[Test]
    public function object_parser_defaults_dependent_required_to_null(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        self::assertNull($parser->build([], $recurse)['dependentRequired']);
        self::assertNull($parser->build(['dependentRequired' => null], $recurse)['dependentRequired']);
    }

    #[Test]
    public function object_parser_normalizes_empty_dependent_required_map_to_null(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        self::assertNull($parser->build(['dependentRequired' => []], $recurse)['dependentRequired']);
    }

    #[Test]
    public function legacy_dependencies_merges_deduplicates_on_key_collision(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build([
            'dependentRequired' => ['a' => ['foo', 'baz']],
            'dependencies' => ['a' => ['foo', 'bar']],
        ], $recurse);

        self::assertSame(['a' => ['foo', 'baz', 'bar']], $args['dependentRequired']);
    }

    #[Test]
    public function legacy_dependencies_schema_form_last_wins_on_key_collision(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: $data['type'] ?? null);

        $args = $parser->build([
            'dependentSchemas' => ['x' => ['type' => 'object']],
            'dependencies' => ['x' => ['type' => 'string']],
        ], $recurse);

        self::assertSame(['x'], array_keys($args['dependentSchemas']));
        self::assertSame('string', $args['dependentSchemas']['x']->type);
    }

    #[Test]
    public function legacy_dependencies_list_form_translates_to_dependent_required(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build(['dependencies' => ['bar' => ['foo']]], $recurse);

        self::assertSame(['bar' => ['foo']], $args['dependentRequired']);
        self::assertNull($args['dependentSchemas']);
    }

    #[Test]
    public function legacy_dependencies_schema_form_translates_to_dependent_schemas(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build(['dependencies' => ['bar' => ['required' => ['foo']]]], $recurse);

        self::assertNull($args['dependentRequired']);
        self::assertArrayHasKey('bar', $args['dependentSchemas']);
        self::assertSame('nested', $args['dependentSchemas']['bar']->type);
    }

    #[Test]
    public function legacy_dependencies_boolean_form_translates_to_dependent_schemas(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(bool|array $data): Schema => new Schema(type: 'nested');

        $args = $parser->build(['dependencies' => ['bar' => false]], $recurse);

        self::assertNull($args['dependentRequired']);
        self::assertArrayHasKey('bar', $args['dependentSchemas']);
        self::assertSame('nested', $args['dependentSchemas']['bar']->type);
    }

    #[Test]
    public function legacy_dependencies_empty_list_translates_vacuously(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build(['dependencies' => ['bar' => []]], $recurse);

        self::assertNull($args['dependentRequired']);
        self::assertNull($args['dependentSchemas']);
    }

    #[Test]
    public function legacy_dependencies_merges_with_explicit_keywords(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build([
            'dependentRequired' => ['a' => ['b']],
            'dependencies' => ['c' => ['d']],
        ], $recurse);

        self::assertSame(['a' => ['b'], 'c' => ['d']], $args['dependentRequired']);
    }

    #[Test]
    public function legacy_dependencies_schema_form_merges_with_explicit_dependent_schemas(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build([
            'dependentSchemas' => ['x' => ['type' => 'object']],
            'dependencies' => ['y' => ['type' => 'string']],
        ], $recurse);

        self::assertSame(['x', 'y'], array_keys($args['dependentSchemas']));
    }

    #[Test]
    public function legacy_dependencies_mixed_forms_translate_into_both_maps(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build([
            'dependencies' => [
                'bar' => ['foo'],
                'baz' => [],
                'quux' => true,
            ],
        ], $recurse);

        self::assertSame(['bar' => ['foo']], $args['dependentRequired']);
        self::assertSame(['quux'], array_keys($args['dependentSchemas']));
    }

    #[Test]
    public function legacy_dependencies_defaults_to_null_when_keyword_absent(): void
    {
        $parser = new ObjectSchemaKeywordParser();
        $recurse = static fn(): Schema => new Schema();

        $args = $parser->build(['required' => ['a']], $recurse);

        self::assertNull($args['dependentRequired']);
        self::assertNull($args['dependentSchemas']);
    }

    #[Test]
    public function legacy_dependencies_throws_on_garbage_dependency_value(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array, boolean or list of strings for schema keyword "dependencies", got int');

        new ObjectSchemaKeywordParser()->build(['dependencies' => ['bar' => 42]], static fn(): Schema => new Schema());
    }

    #[Test]
    public function legacy_dependencies_throws_on_garbage_list_element(): void
    {
        $parser = new ObjectSchemaKeywordParser();

        $this->expectException(TypeError::class);

        $parser->build(['dependencies' => ['bar' => [42]]], static fn(): Schema => new Schema());
    }

    #[Test]
    public function same_input_same_outcome_across_keyword_parsers(): void
    {
        $recurse = static fn(): Schema => new Schema();

        $outcomes = [
            'items' => $this->outcome(static fn(): array => new ArraySchemaKeywordParser()->build(['items' => 42], $recurse)),
            'propertyNames' => $this->outcome(static fn(): array => new ObjectSchemaKeywordParser()->build(['propertyNames' => 42], $recurse)),
            'additionalProperties' => $this->outcome(static fn(): array => new ObjectSchemaKeywordParser()->build(['additionalProperties' => 42], $recurse)),
            'unevaluatedProperties' => $this->outcome(static fn(): array => new ObjectSchemaKeywordParser()->build(['unevaluatedProperties' => 42], $recurse)),
            'contentSchema' => $this->outcome(static fn(): array => new ObjectSchemaKeywordParser()->build(['contentSchema' => 42], $recurse)),
            'not' => $this->outcome(static fn(): array => new CompositionSchemaKeywordParser()->build(['not' => 42], $recurse)),
            'allOf element' => $this->outcome(static fn(): array => new CompositionSchemaKeywordParser()->build(['allOf' => [['type' => 'string'], 42]], $recurse)),
            'prefixItems element' => $this->outcome(static fn(): array => new ArraySchemaKeywordParser()->build(['prefixItems' => [['type' => 'string'], 42]], $recurse)),
        ];

        foreach ($outcomes as $keyword => $outcome) {
            self::assertSame(InvalidSchemaException::class, $outcome, sprintf('Keyword "%s" outcome mismatch', $keyword));
        }
    }

    public static function garbage_keyword_value_provider(): array
    {
        return [
            'int' => [42, 'int'],
            'float' => [1.5, 'float'],
            'string' => ['str', 'string'],
        ];
    }

    public static function garbage_optional_schema_keyword_provider(): array
    {
        return [
            'additionalProperties int' => ['additionalProperties', 42],
            'additionalProperties string' => ['additionalProperties', 'str'],
            'unevaluatedProperties int' => ['unevaluatedProperties', 0],
            'contentSchema string' => ['contentSchema', 'str'],
        ];
    }

    private function outcome(Closure $build): string
    {
        try {
            $build();

            return 'no-exception';
        } catch (InvalidSchemaException) {
            return InvalidSchemaException::class;
        }
    }
}
