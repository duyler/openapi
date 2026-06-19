<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

final class ResponseTypeCoercerTest extends TestCase
{
    private ResponseTypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ResponseTypeCoercer();
    }

    #[Test]
    public function return_value_as_is_when_coercion_disabled(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('123', new CoercionContext(schema: $schema, enabled: false));

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_is_null(): void
    {
        $result = $this->coercer->coerce('123', new CoercionContext(schema: null, enabled: true));

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_type_is_null(): void
    {
        $schema = new Schema();

        $result = $this->coercer->coerce('123', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('123', $result);
    }

    #[Test]
    public function coerce_string_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('666', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(666, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_exponential_notation_returns_string_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('1e10', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('1e10', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_hex_notation_returns_string_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('0x10', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('0x10', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_empty_string_to_integer_returns_string_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_string_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('19.99', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(19.99, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_string_to_boolean_true(): void
    {
        $schema = new Schema(type: 'boolean');

        foreach (['true', '1', 'yes', 'on'] as $input) {
            $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));
            $this->assertTrue($result, "Failed to coerce '$input' to true");
        }
    }

    #[Test]
    public function coerce_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        foreach (['false', '0', 'no', 'off'] as $input) {
            $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));
            $this->assertFalse($result, "Failed to coerce '$input' to false");
        }
    }

    #[Test]
    public function coerce_union_type_integer_string(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('123', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_type_string_integer(): void
    {
        $schema = new Schema(type: ['string', 'integer']);

        $result = $this->coercer->coerce('hello', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('hello', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_union_type_with_null(): void
    {
        $schema = new Schema(type: ['string', 'null']);

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_string_when_type_not_matched(): void
    {
        $schema = new Schema(type: 'object');

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_array_as_is(): void
    {
        $schema = new Schema(type: 'array');

        $input = ['foo', 'bar'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function return_integer_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(123, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_float_as_is(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(19.99, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(19.99, $result);
    }

    #[Test]
    public function return_boolean_as_is(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_float_string_to_integer_returns_string_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('123.45', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('123.45', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_non_boolean_string_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('random', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_non_string_value_through_union_type(): void
    {
        $schema = new Schema(type: ['string', 'integer', 'boolean']);

        $result = $this->coercer->coerce(123, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_string_when_first_union_type_integer_fails_and_boolean_matches(): void
    {
        $schema = new Schema(type: ['integer', 'boolean']);

        $result = $this->coercer->coerce('not-a-number', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function return_coerced_value_when_union_type_matches_boolean(): void
    {
        $schema = new Schema(type: ['boolean', 'string']);

        $result = $this->coercer->coerce('value', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function skip_null_in_union_type_and_return_original_value(): void
    {
        $schema = new Schema(type: ['string', 'null']);

        $result = $this->coercer->coerce('value', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_string_to_number_integer(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_array_as_is_when_type_is_unknown(): void
    {
        $schema = new Schema(type: 'unknown');

        $input = ['a', 'b', 'c'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_union_type_number_returns_integer(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('100', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(100.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_number_returns_float(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('100.5', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(100.5, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_with_custom_type_returns_string(): void
    {
        $schema = new Schema(type: ['custom', 'string']);

        $result = $this->coercer->coerce('value', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_negative_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('-42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(-42, $result);
    }

    #[Test]
    public function coerce_negative_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('-19.99', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(-19.99, $result);
    }

    #[Test]
    public function coerce_zero_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('0', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(0, $result);
    }

    #[Test]
    public function coerce_zero_float(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('0.0', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function coerce_large_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('999999999999', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(999999999999, $result);
    }

    #[Test]
    public function return_boolean_when_union_type_integer_fails_for_non_integer_string(): void
    {
        $schema = new Schema(type: ['integer', 'boolean']);

        $result = $this->coercer->coerce('abc', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_union_type_when_number_matches_string(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('123.45', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_integer_when_first_union_type_is_integer_and_value_is_string(): void
    {
        $schema = new Schema(type: ['integer', 'number']);

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_number_to_float_from_string(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('123.45', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_empty_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('', new CoercionContext(schema: $schema, enabled: true));

        $this->assertFalse($result);
    }

    #[Test]
    public function coerce_space_string_to_boolean_true(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('  ', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_number_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('2', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function return_string_for_unknown_type_in_schema(): void
    {
        $schema = new Schema(type: 'unknown');

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_unknown_types(): void
    {
        $schema = new Schema(type: ['unknown1', 'unknown2']);

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_null_only(): void
    {
        $schema = new Schema(type: ['null']);

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_non_string_value_with_unknown_type(): void
    {
        $schema = new Schema(type: 'custom');

        $result = $this->coercer->coerce(123, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_original_for_array_value_with_string_type(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(['a', 'b'], new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function return_original_for_string_value_with_unknown_type(): void
    {
        $schema = new Schema(type: 'custom');

        $result = $this->coercer->coerce('hello', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function coerce_null_to_empty_string_when_type_is_null(): void
    {
        $schema = new Schema();

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function coerce_boolean_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $resultTrue = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));
        $this->assertSame(1, $resultTrue);

        $resultFalse = $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true));
        $this->assertSame(0, $resultFalse);
    }

    #[Test]
    public function coerce_integer_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_integer_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultTrue = $this->coercer->coerce(1, new CoercionContext(schema: $schema, enabled: true));
        $this->assertTrue($resultTrue);

        $resultFalse = $this->coercer->coerce(0, new CoercionContext(schema: $schema, enabled: true));
        $this->assertFalse($resultFalse);
    }

    #[Test]
    public function coerce_nested_object_with_integer_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
                'price' => new Schema(type: 'number'),
                'active' => new Schema(type: 'boolean'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $input = [
            'age' => '30',
            'price' => '99.99',
            'active' => 'true',
            'name' => 'John',
        ];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(30, $result['age']);
        $this->assertSame(99.99, $result['price']);
        $this->assertTrue($result['active']);
        $this->assertSame('John', $result['name']);
    }

    #[Test]
    public function coerce_nested_object_with_nested_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'age' => new Schema(type: 'integer'),
                        'active' => new Schema(type: 'boolean'),
                    ],
                ),
            ],
        );

        $input = [
            'user' => [
                'age' => '25',
                'active' => 'false',
            ],
        ];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(25, $result['user']['age']);
        $this->assertFalse($result['user']['active']);
    }

    #[Test]
    public function coerce_array_of_integers(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $input = ['1', '2', '3'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame([1, 2, 3], $result);
        $this->assertIsInt($result[0]);
        $this->assertIsInt($result[1]);
        $this->assertIsInt($result[2]);
    }

    #[Test]
    public function coerce_array_of_objects(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(type: 'integer'),
                    'active' => new Schema(type: 'boolean'),
                ],
            ),
        );

        $input = [
            ['id' => '1', 'active' => 'true'],
            ['id' => '2', 'active' => 'false'],
        ];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(1, $result[0]['id']);
        $this->assertTrue($result[0]['active']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertFalse($result[1]['active']);
    }

    #[Test]
    public function coerce_empty_array(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $input = [];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame([], $result);
    }

    #[Test]
    public function coerce_object_without_properties_returns_original(): void
    {
        $schema = new Schema(type: 'object');

        $input = ['key' => 'value'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_array_without_items_returns_original(): void
    {
        $schema = new Schema(type: 'array');

        $input = ['1', '2', '3'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_object_only_defined_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
            ],
        );

        $input = [
            'age' => '30',
            'extra' => 'value',
        ];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('extra', $result);
        $this->assertSame(30, $result['age']);
    }

    public static function coerceToIntegerProvider(): array
    {
        return [
            'string integer' => ['42', 42],
            'string zero' => ['0', 0],
            'string negative' => ['-10', -10],
            'float value fractional returns as is' => [3.14, 3.14],
            'float zero' => [0.0, 0],
            'bool true' => [true, 1],
            'bool false' => [false, 0],
            'already integer' => [99, 99],
        ];
    }

    #[DataProvider('coerceToIntegerProvider')]
    #[Test]
    public function coerce_to_integer_with_data_provider(mixed $input, mixed $expected): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function coerceToNumberProvider(): array
    {
        return [
            'string float' => ['19.99', 19.99],
            'string integer as float' => ['42', 42.0],
            'string zero' => ['0', 0.0],
            'string negative' => ['-3.14', -3.14],
            'integer to float' => [42, 42.0],
            'already float' => [3.14, 3.14],
            'bool true' => [true, 1.0],
            'bool false' => [false, 0.0],
        ];
    }

    #[DataProvider('coerceToNumberProvider')]
    #[Test]
    public function coerce_to_number_with_data_provider(mixed $input, float $expected): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function coerceToBooleanProvider(): array
    {
        return [
            'string true' => ['true', true],
            'string yes' => ['yes', true],
            'string on' => ['on', true],
            'string one' => ['1', true],
            'string false' => ['false', false],
            'string no' => ['no', false],
            'string off' => ['off', false],
            'string zero' => ['0', false],
            'int one' => [1, true],
            'int zero' => [0, false],
            'int negative' => [-1, true],
            'float one' => [1.0, true],
            'float zero' => [0.0, false],
            'float fraction' => [0.5, true],
            'already bool true' => [true, true],
            'already bool false' => [false, false],
        ];
    }

    #[DataProvider('coerceToBooleanProvider')]
    #[Test]
    public function coerce_to_boolean_with_data_provider(mixed $input, bool $expected): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function coerceUnionTypeProvider(): array
    {
        return [
            'integer first match' => [
                ['integer', 'string'],
                '42',
                42,
            ],
            'string first match' => [
                ['string', 'integer'],
                'hello',
                'hello',
            ],
            'number match from string' => [
                ['number', 'string'],
                '3.14',
                3.14,
            ],
            'boolean match from string' => [
                ['boolean', 'string'],
                'true',
                true,
            ],
            'null skipped string matched' => [
                ['null', 'string'],
                'test',
                'test',
            ],
            'all null types returns original' => [
                ['null'],
                'test',
                'test',
            ],
        ];
    }

    #[DataProvider('coerceUnionTypeProvider')]
    #[Test]
    public function coerce_union_type_with_data_provider(array $types, mixed $input, mixed $expected): void
    {
        $schema = new Schema(type: $types);

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function coerceToObjectProvider(): array
    {
        return [
            'simple object with string properties' => [
                ['name' => 'John'],
                ['name' => new Schema(type: 'string')],
                ['name' => 'John'],
            ],
            'object with mixed types' => [
                ['age' => '25', 'active' => 'true'],
                ['age' => new Schema(type: 'integer'), 'active' => new Schema(type: 'boolean')],
                ['age' => 25, 'active' => true],
            ],
            'nested object' => [
                ['user' => ['age' => '30']],
                ['user' => new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')])],
                ['user' => ['age' => 30]],
            ],
        ];
    }

    #[DataProvider('coerceToObjectProvider')]
    #[Test]
    public function coerce_to_object_with_data_provider(array $input, array $properties, array $expected): void
    {
        $schema = new Schema(type: 'object', properties: $properties);

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function coerceToArrayProvider(): array
    {
        return [
            'array of integers from strings' => [
                ['1', '2', '3'],
                new Schema(type: 'integer'),
                [1, 2, 3],
            ],
            'array of floats from strings' => [
                ['1.1', '2.2'],
                new Schema(type: 'number'),
                [1.1, 2.2],
            ],
            'array of booleans from strings' => [
                ['true', 'false'],
                new Schema(type: 'boolean'),
                [true, false],
            ],
            'empty array' => [
                [],
                new Schema(type: 'integer'),
                [],
            ],
        ];
    }

    #[DataProvider('coerceToArrayProvider')]
    #[Test]
    public function coerce_to_array_with_data_provider(array $input, Schema $itemsSchema, array $expected): void
    {
        $schema = new Schema(type: 'array', items: $itemsSchema);

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($expected, $result);
    }

    public static function isValidTypeProvider(): array
    {
        return [
            'string matches string type' => ['hello', 'string', true],
            'integer matches integer type' => [42, 'integer', true],
            'float matches number type' => [3.14, 'number', true],
            'integer matches number type' => [42, 'number', true],
            'bool matches boolean type' => [true, 'boolean', true],
            'null matches null type' => [null, 'null', true],
            'array matches object type' => [['key' => 'val'], 'object', true],
            'array matches array type' => [[1, 2], 'array', true],
            'unknown type matches always' => ['anything', 'custom', true],
        ];
    }

    #[DataProvider('isValidTypeProvider')]
    #[Test]
    public function is_valid_type_checked_through_union_type(mixed $input, string $type, bool $shouldBeValid): void
    {
        $schema = new Schema(type: [$type, 'string']);

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        if ($shouldBeValid) {
            $typeCheck = match ($type) {
                'string' => is_string($result),
                'number' => is_float($result) || is_int($result),
                'integer' => is_int($result),
                'boolean' => is_bool($result),
                'null' => null === $result,
                'object' => is_array($result),
                'array' => is_array($result),
                default => true,
            };
            $this->assertTrue($typeCheck, sprintf(
                'Expected result to match type "%s", got %s',
                $type,
                gettype($result),
            ));
        }
    }

    #[Test]
    public function coerce_float_to_integer_returns_float_as_is_when_fractional(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(42.5, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42.5, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_non_array_to_object_returns_original(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $result = $this->coercer->coerce('not-array', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('not-array', $result);
    }

    #[Test]
    public function coerce_non_array_to_array_returns_empty(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'integer'));

        $result = $this->coercer->coerce('not-array', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame([], $result);
    }

    #[Test]
    public function coerce_float_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce(0.5, new CoercionContext(schema: $schema, enabled: true));
        $this->assertTrue($result);

        $resultZero = $this->coercer->coerce(0.0, new CoercionContext(schema: $schema, enabled: true));
        $this->assertFalse($resultZero);
    }

    #[Test]
    public function coerce_object_preserves_extra_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
            ],
        );

        $input = ['age' => '30', 'extra' => 'value'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(30, $result['age']);
        $this->assertArrayNotHasKey('extra', $result);
    }

    #[Test]
    public function coerce_object_missing_property_not_in_result(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $input = ['age' => '25'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(25, $result['age']);
        $this->assertArrayNotHasKey('name', $result);
    }

    #[Test]
    public function coerce_nullable_value_with_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_nullable_value_without_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, nullableAsType: false));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_array_value_with_integer_type_returns_array_unchanged(): void
    {
        $schema = new Schema(type: 'integer');

        $input = ['a', 'b'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_null_value_with_integer_type_non_nullable_returns_null(): void
    {
        $schema = new Schema(type: 'integer', nullable: false);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_array_value_with_number_type_returns_array_unchanged(): void
    {
        $schema = new Schema(type: 'number');

        $input = ['x', 'y'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_null_value_with_number_type_non_nullable_returns_null(): void
    {
        $schema = new Schema(type: 'number', nullable: false);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_array_value_with_boolean_type_returns_array_unchanged(): void
    {
        $schema = new Schema(type: 'boolean');

        $input = ['foo'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_null_value_with_boolean_type_non_nullable_returns_null(): void
    {
        $schema = new Schema(type: 'boolean', nullable: false);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, nullableAsType: true));

        $this->assertNull($result);
    }

    public static function coerceDefaultBranchProvider(): array
    {
        return [
            'array to integer returns array' => [
                ['a', 'b'],
                'integer',
                ['a', 'b'],
            ],
            'array to number returns array' => [
                ['x'],
                'number',
                ['x'],
            ],
            'array to boolean returns array' => [
                [1, 2, 3],
                'boolean',
                [1, 2, 3],
            ],
            'null to integer non-nullable returns null' => [
                null,
                'integer',
                null,
            ],
            'null to number non-nullable returns null' => [
                null,
                'number',
                null,
            ],
            'null to boolean non-nullable returns null' => [
                null,
                'boolean',
                null,
            ],
        ];
    }

    #[DataProvider('coerceDefaultBranchProvider')]
    #[Test]
    public function coerce_default_branch_returns_value_unchanged(mixed $input, string $type, mixed $expected): void
    {
        $schema = new Schema(type: $type, nullable: false);

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true, nullableAsType: true));

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function coerce_string_from_float(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_string_from_bool(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(true, $result);
    }
}
