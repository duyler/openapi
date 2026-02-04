<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TypeCoercerTest extends TestCase
{
    private TypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new TypeCoercer();
    }

    #[Test]
    public function return_value_as_is_when_coercion_disabled(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('123', $param, false);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_is_null(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
        );

        $result = $this->coercer->coerce('123', $param, true);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function return_value_as_is_when_schema_type_is_null(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(),
        );

        $result = $this->coercer->coerce('123', $param, true);

        $this->assertSame('123', $result);
    }

    #[Test]
    public function coerce_string_to_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('666', $param, true);

        $this->assertSame(666, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_exponential_notation(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('1e10', $param, true);

        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_string_to_integer_with_hex_notation(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('0x10', $param, true);

        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_empty_string_to_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('', $param, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function return_object_as_array(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'object'),
        );

        $input = new stdClass();
        $input->prop = 'value';
        $result = $this->coercer->coerce($input, $param, true);

        $this->assertIsArray($result);
        $this->assertSame(['prop' => 'value'], $result);
    }

    #[Test]
    public function return_string_from_unknown_type(): void
    {
        $resource = fopen('php://memory', 'r');
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'string'),
        );

        $result = $this->coercer->coerce($resource, $param, true);

        $this->assertIsString($result);
        fclose($resource);
    }

    #[Test]
    public function coerce_string_to_number(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce('19.99', $param, true);

        $this->assertSame(19.99, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_string_to_boolean_true(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        foreach (['true', '1', 'yes', 'on'] as $input) {
            $result = $this->coercer->coerce($input, $param, true);
            $this->assertTrue($result, "Failed to coerce '$input' to true");
        }
    }

    #[Test]
    public function coerce_string_to_boolean_false(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        foreach (['false', '0', 'no', 'off'] as $input) {
            $result = $this->coercer->coerce($input, $param, true);
            $this->assertFalse($result, "Failed to coerce '$input' to false");
        }
    }

    #[Test]
    public function coerce_union_type_integer_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['integer', 'string']),
        );

        $result = $this->coercer->coerce('123', $param, true);

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_type_string_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['string', 'integer']),
        );

        $result = $this->coercer->coerce('hello', $param, true);

        $this->assertSame('hello', $result);
        $this->assertIsString($result);
    }

    #[Test]
    public function coerce_union_type_with_null(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['string', 'null']),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_string_when_type_not_matched(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'object'),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_array_as_is(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'array'),
        );

        $input = ['foo', 'bar'];
        $result = $this->coercer->coerce($input, $param, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function return_integer_as_is(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce(123, $param, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_float_as_is(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce(19.99, $param, true);

        $this->assertSame(19.99, $result);
    }

    #[Test]
    public function return_boolean_as_is(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        $result = $this->coercer->coerce(true, $param, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function convert_null_to_empty_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'string'),
        );

        $result = $this->coercer->coerce(null, $param, true);

        $this->assertSame('', $result);
    }

    #[Test]
    public function coerce_float_string_to_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('123.45', $param, true);

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_non_boolean_string_to_boolean(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        $result = $this->coercer->coerce('random', $param, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_non_string_value_through_union_type(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['string', 'integer', 'boolean']),
        );

        $result = $this->coercer->coerce(123, $param, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_coerced_value_when_union_type_matches_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['integer', 'boolean']),
        );

        $result = $this->coercer->coerce('not-a-number', $param, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function return_coerced_value_when_union_type_matches_boolean(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['boolean', 'string']),
        );

        $result = $this->coercer->coerce('value', $param, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function skip_null_in_union_type_and_return_original_value(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['string', 'null']),
        );

        $result = $this->coercer->coerce('value', $param, true);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_string_to_number_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce('42', $param, true);

        $this->assertSame(42.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_array_as_is_when_type_is_unknown(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'unknown'),
        );

        $input = ['a', 'b', 'c'];
        $result = $this->coercer->coerce($input, $param, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_union_type_number_returns_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['number', 'string']),
        );

        $result = $this->coercer->coerce('100', $param, true);

        $this->assertSame(100.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_number_returns_float(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['number', 'string']),
        );

        $result = $this->coercer->coerce('100.5', $param, true);

        $this->assertSame(100.5, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_union_type_with_custom_type_returns_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['custom', 'string']),
        );

        $result = $this->coercer->coerce('value', $param, true);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function coerce_negative_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('-42', $param, true);

        $this->assertSame(-42, $result);
    }

    #[Test]
    public function coerce_negative_number(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce('-19.99', $param, true);

        $this->assertSame(-19.99, $result);
    }

    #[Test]
    public function coerce_zero_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('0', $param, true);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function coerce_zero_float(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce('0.0', $param, true);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function coerce_large_integer(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('999999999999', $param, true);

        $this->assertSame(999999999999, $result);
    }

    #[Test]
    public function return_integer_when_union_type_integer_matches_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['integer', 'boolean']),
        );

        $result = $this->coercer->coerce('abc', $param, true);

        $this->assertSame(0, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_type_when_number_matches_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['number', 'string']),
        );

        $result = $this->coercer->coerce('123.45', $param, true);

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function return_integer_when_first_union_type_is_integer_and_value_is_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['integer', 'number']),
        );

        $result = $this->coercer->coerce('42', $param, true);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_union_number_to_float_from_string(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['number', 'string']),
        );

        $result = $this->coercer->coerce('123.45', $param, true);

        $this->assertSame(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_empty_string_to_boolean_true(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        $result = $this->coercer->coerce('', $param, true);

        $this->assertFalse($result);
    }

    #[Test]
    public function coerce_space_string_to_boolean_true(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        $result = $this->coercer->coerce('  ', $param, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_number_string_to_boolean_false(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'boolean'),
        );

        $result = $this->coercer->coerce('2', $param, true);

        $this->assertTrue($result);
    }

    #[Test]
    public function return_string_for_unknown_type_in_schema(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'unknown'),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_unknown_types(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['unknown1', 'unknown2']),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_union_type_with_null_only(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: ['null']),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function return_original_for_non_string_value_with_unknown_type(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'custom'),
        );

        $result = $this->coercer->coerce(123, $param, true);

        $this->assertSame(123, $result);
    }

    #[Test]
    public function return_original_for_array_value_with_string_type(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'string'),
        );

        $result = $this->coercer->coerce(['a', 'b'], $param, true);

        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function return_original_for_string_value_with_unknown_type(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(type: 'custom'),
        );

        $result = $this->coercer->coerce('hello', $param, true);

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function coerce_null_to_empty_string_when_type_is_null(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'path',
            schema: new Schema(),
        );

        $result = $this->coercer->coerce('test', $param, true);

        $this->assertSame('test', $result);
    }
}
