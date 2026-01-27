<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $result = $this->coercer->coerce('123', $schema, false);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_is_null(): void
    {
        $result = $this->coercer->coerce('123', null, true);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_type_is_null(): void
    {
        $schema = new Schema();

        $result = $this->coercer->coerce('123', $schema, true);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function coerce_string_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('666', $schema, true);

        $this->assertSame(666, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_exponential_notation(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('1e10', $schema, true);

        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_hex_notation(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('0x10', $schema, true);

        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_empty_string_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('', $schema, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('19.99', $schema, true);

        $this->assertSame(19.99, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_string_to_boolean_true(): void
    {
        $schema = new Schema(type: 'boolean');

        foreach (['true', '1', 'yes', 'on'] as $input) {
            $result = $this->coercer->coerce($input, $schema, true);
            $this->assertTrue($result, "Failed to coerce '$input' to true");
        }
    }

    #[Test]
    public function coerce_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        foreach (['false', '0', 'no', 'off'] as $input) {
            $result = $this->coercer->coerce($input, $schema, true);
            $this->assertFalse($result, "Failed to coerce '$input' to false");
        }
    }

    #[Test]
    public function coerce_union_type_integer_string(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('123', $schema, true);

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_type_string_integer(): void
    {
        $schema = new Schema(type: ['string', 'integer']);

        $result = $this->coercer->coerce('hello', $schema, true);

        $this->assertSame('hello', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_union_type_with_null(): void
    {
        $schema = new Schema(type: ['string', 'null']);

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_string_when_type_not_matched(): void
    {
        $schema = new Schema(type: 'object');

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_array_as_is(): void
    {
        $schema = new Schema(type: 'array');

        $input = ['foo', 'bar'];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function return_integer_as_is(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(123, $schema, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_float_as_is(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(19.99, $schema, true);

        $this->assertSame(19.99, $result);
    }

    #[Test]
    public function return_boolean_as_is(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce(true, $schema, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_float_string_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('123.45', $schema, true);

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_non_boolean_string_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('random', $schema, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_non_string_value_through_union_type(): void
    {
        $schema = new Schema(type: ['string', 'integer', 'boolean']);

        $result = $this->coercer->coerce(123, $schema, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_coerced_value_when_union_type_matches_integer(): void
    {
        $schema = new Schema(type: ['integer', 'boolean']);

        $result = $this->coercer->coerce('not-a-number', $schema, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function return_coerced_value_when_union_type_matches_boolean(): void
    {
        $schema = new Schema(type: ['boolean', 'string']);

        $result = $this->coercer->coerce('value', $schema, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function skip_null_in_union_type_and_return_original_value(): void
    {
        $schema = new Schema(type: ['string', 'null']);

        $result = $this->coercer->coerce('value', $schema, true);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_string_to_number_integer(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('42', $schema, true);

        $this->assertSame(42.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_array_as_is_when_type_is_unknown(): void
    {
        $schema = new Schema(type: 'unknown');

        $input = ['a', 'b', 'c'];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_union_type_number_returns_integer(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('100', $schema, true);

        $this->assertSame(100.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_number_returns_float(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('100.5', $schema, true);

        $this->assertSame(100.5, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_with_custom_type_returns_string(): void
    {
        $schema = new Schema(type: ['custom', 'string']);

        $result = $this->coercer->coerce('value', $schema, true);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_negative_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('-42', $schema, true);

        $this->assertSame(-42, $result);
    }

    #[Test]
    public function coerce_negative_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('-19.99', $schema, true);

        $this->assertSame(-19.99, $result);
    }

    #[Test]
    public function coerce_zero_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('0', $schema, true);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function coerce_zero_float(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('0.0', $schema, true);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function coerce_large_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('999999999999', $schema, true);

        $this->assertSame(999999999999, $result);
    }

    #[Test]
    public function return_integer_when_union_type_integer_matches_string(): void
    {
        $schema = new Schema(type: ['integer', 'boolean']);

        $result = $this->coercer->coerce('abc', $schema, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_type_when_number_matches_string(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('123.45', $schema, true);

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_integer_when_first_union_type_is_integer_and_value_is_string(): void
    {
        $schema = new Schema(type: ['integer', 'number']);

        $result = $this->coercer->coerce('42', $schema, true);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_number_to_float_from_string(): void
    {
        $schema = new Schema(type: ['number', 'string']);

        $result = $this->coercer->coerce('123.45', $schema, true);

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_empty_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('', $schema, true);

        $this->assertFalse($result);
    }

    #[Test]
    public function coerce_space_string_to_boolean_true(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('  ', $schema, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_number_string_to_boolean_false(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('2', $schema, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function return_string_for_unknown_type_in_schema(): void
    {
        $schema = new Schema(type: 'unknown');

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_unknown_types(): void
    {
        $schema = new Schema(type: ['unknown1', 'unknown2']);

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_null_only(): void
    {
        $schema = new Schema(type: ['null']);

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_non_string_value_with_unknown_type(): void
    {
        $schema = new Schema(type: 'custom');

        $result = $this->coercer->coerce(123, $schema, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_original_for_array_value_with_string_type(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(['a', 'b'], $schema, true);

        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function return_original_for_string_value_with_unknown_type(): void
    {
        $schema = new Schema(type: 'custom');

        $result = $this->coercer->coerce('hello', $schema, true);

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function coerce_null_to_empty_string_when_type_is_null(): void
    {
        $schema = new Schema();

        $result = $this->coercer->coerce('test', $schema, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function coerce_boolean_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $resultTrue = $this->coercer->coerce(true, $schema, true);
        $this->assertSame(1, $resultTrue);

        $resultFalse = $this->coercer->coerce(false, $schema, true);
        $this->assertSame(0, $resultFalse);
    }

    #[Test]
    public function coerce_integer_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, $schema, true);

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_integer_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultTrue = $this->coercer->coerce(1, $schema, true);
        $this->assertTrue($resultTrue);

        $resultFalse = $this->coercer->coerce(0, $schema, true);
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

        $result = $this->coercer->coerce($input, $schema, true);

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

        $result = $this->coercer->coerce($input, $schema, true);

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

        $result = $this->coercer->coerce($input, $schema, true);

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

        $result = $this->coercer->coerce($input, $schema, true);

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

        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame([], $result);
    }

    #[Test]
    public function coerce_object_without_properties_returns_original(): void
    {
        $schema = new Schema(type: 'object');

        $input = ['key' => 'value'];

        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_array_without_items_returns_original(): void
    {
        $schema = new Schema(type: 'array');

        $input = ['1', '2', '3'];

        $result = $this->coercer->coerce($input, $schema, true);

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

        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('extra', $result);
        $this->assertSame(30, $result['age']);
    }
}
