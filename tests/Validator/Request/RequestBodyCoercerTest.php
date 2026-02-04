<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestBodyCoercerTest extends TestCase
{
    private RequestBodyCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new RequestBodyCoercer();
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
    public function coerce_object_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
                'active' => new Schema(type: 'boolean'),
            ],
        );

        $input = ['age' => '25', 'active' => 'true', 'extra' => 'value'];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame('value', $result['extra']);
    }

    #[Test]
    public function coerce_array_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $input = ['1', '2', '3'];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function coerce_nested_object(): void
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

        $input = ['user' => ['age' => '25', 'active' => 'true']];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame(25, $result['user']['age']);
        $this->assertTrue($result['user']['active']);
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
                    'name' => new Schema(type: 'string'),
                ],
            ),
        );

        $input = [
            ['id' => '1', 'name' => 'Alice'],
            ['id' => '2', 'name' => 'Bob'],
        ];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertSame('Bob', $result[1]['name']);
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_string_to_number_with_strict_mode(): void
    {
        $schema = new Schema(type: 'number');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not-a-number', $schema, true, true);
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_string_to_integer_with_strict_mode(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not-a-number', $schema, true, true);
    }

    #[Test]
    public function throw_type_mismatch_error_for_float_to_integer_with_strict_mode(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce(3.14, $schema, true, true);
    }

    #[Test]
    public function coerce_non_strict_mode_returns_zero_for_invalid_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('not-a-number', $schema, true, false);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function return_null_when_nullable_and_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, $schema, true, false, true);

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_union_type(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('123', $schema, true, false);

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
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
    public function coerce_integer_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, $schema, true);

        $this->assertSame(42.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_float_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(3.14, $schema, true, false);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function coerce_boolean_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $resultTrue = $this->coercer->coerce(true, $schema, true);
        $resultFalse = $this->coercer->coerce(false, $schema, true);

        $this->assertSame(1, $resultTrue);
        $this->assertSame(0, $resultFalse);
    }

    #[Test]
    public function coerce_boolean_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $resultTrue = $this->coercer->coerce(true, $schema, true);
        $resultFalse = $this->coercer->coerce(false, $schema, true);

        $this->assertSame(1.0, $resultTrue);
        $this->assertSame(0.0, $resultFalse);
    }

    #[Test]
    public function coerce_integer_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultZero = $this->coercer->coerce(0, $schema, true);
        $resultNonZero = $this->coercer->coerce(1, $schema, true);

        $this->assertFalse($resultZero);
        $this->assertTrue($resultNonZero);
    }

    #[Test]
    public function coerce_float_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultZero = $this->coercer->coerce(0.0, $schema, true);
        $resultNonZero = $this->coercer->coerce(1.5, $schema, true);

        $this->assertFalse($resultZero);
        $this->assertTrue($resultNonZero);
    }

    #[Test]
    public function return_object_as_is_when_properties_null(): void
    {
        $schema = new Schema(type: 'object');

        $input = ['prop' => 'value'];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame($input, $result);
    }

    #[Test]
    public function return_empty_array_for_non_array_value_to_array(): void
    {
        $schema = new Schema(type: 'array');

        $result = $this->coercer->coerce('not-an-array', $schema, true);

        $this->assertSame([], $result);
    }

    #[Test]
    public function return_array_as_is_when_items_null(): void
    {
        $schema = new Schema(type: 'array');

        $input = [1, 2, 3];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame($input, $result);
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
    public function coerce_large_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('999999999999', $schema, true);

        $this->assertSame(999999999999, $result);
    }

    #[Test]
    public function return_original_for_non_array_value_to_object(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $result = $this->coercer->coerce('not-an-object', $schema, true);

        $this->assertSame('not-an-object', $result);
    }

    #[Test]
    public function coerce_deeply_nested_structure(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'level1' => new Schema(
                    type: 'object',
                    properties: [
                        'level2' => new Schema(
                            type: 'object',
                            properties: [
                                'value' => new Schema(type: 'integer'),
                            ],
                        ),
                    ],
                ),
            ],
        );

        $input = ['level1' => ['level2' => ['value' => '42']]];
        $result = $this->coercer->coerce($input, $schema, true);

        $this->assertSame(42, $result['level1']['level2']['value']);
    }
}
