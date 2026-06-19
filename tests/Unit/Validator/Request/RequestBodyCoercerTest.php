<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
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
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame('value', $result['extra']);
    }

    #[Test]
    public function coerce_to_array_processes_items_request(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $input = ['1', '2'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame([1, 2], $result);
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
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

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
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

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

        $this->coercer->coerce('not-a-number', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_string_to_integer_with_strict_mode(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not-a-number', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function throw_type_mismatch_error_for_float_to_integer_with_strict_mode(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_non_strict_mode_returns_value_as_is_for_invalid_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('not-a-number', new CoercionContext(schema: $schema, enabled: true, strict: false));

        $this->assertSame('not-a-number', $result);
    }

    #[Test]
    public function return_null_when_nullable_and_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, strict: false, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_union_type(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('123', new CoercionContext(schema: $schema, enabled: true, strict: false));

        $this->assertSame(123, $result);
        $this->assertIsInt($result);
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
    public function coerce_integer_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42.0, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_float_to_integer_returns_float_as_is_when_fractional(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true, strict: false));

        $this->assertSame(3.14, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function coerce_boolean_to_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $resultTrue = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));
        $resultFalse = $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(1, $resultTrue);
        $this->assertSame(0, $resultFalse);
    }

    #[Test]
    public function coerce_boolean_to_number(): void
    {
        $schema = new Schema(type: 'number');

        $resultTrue = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));
        $resultFalse = $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(1.0, $resultTrue);
        $this->assertSame(0.0, $resultFalse);
    }

    #[Test]
    public function coerce_integer_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultZero = $this->coercer->coerce(0, new CoercionContext(schema: $schema, enabled: true));
        $resultNonZero = $this->coercer->coerce(1, new CoercionContext(schema: $schema, enabled: true));

        $this->assertFalse($resultZero);
        $this->assertTrue($resultNonZero);
    }

    #[Test]
    public function coerce_float_to_boolean(): void
    {
        $schema = new Schema(type: 'boolean');

        $resultZero = $this->coercer->coerce(0.0, new CoercionContext(schema: $schema, enabled: true));
        $resultNonZero = $this->coercer->coerce(1.5, new CoercionContext(schema: $schema, enabled: true));

        $this->assertFalse($resultZero);
        $this->assertTrue($resultNonZero);
    }

    #[Test]
    public function return_object_as_is_when_properties_null(): void
    {
        $schema = new Schema(type: 'object');

        $input = ['prop' => 'value'];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
    }

    #[Test]
    public function coerce_to_array_returns_non_array_as_is_request(): void
    {
        $schema = new Schema(type: 'array');

        $result = $this->coercer->coerce('hello', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function return_array_as_is_when_items_null(): void
    {
        $schema = new Schema(type: 'array');

        $input = [1, 2, 3];
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame($input, $result);
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
    public function coerce_large_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('999999999999', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(999999999999, $result);
    }

    #[Test]
    public function return_original_for_non_array_value_to_object(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $result = $this->coercer->coerce('not-an-object', new CoercionContext(schema: $schema, enabled: true));

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
        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result['level1']['level2']['value']);
    }

    public static function coerceToIntegerProvider(): array
    {
        return [
            'string integer' => ['42', 42],
            'string zero' => ['0', 0],
            'string negative' => ['-10', -10],
            'float value fractional returns as is' => [3.14, 3.14],
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
    public function coerce_string_to_string(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('42', $result);
    }

    #[Test]
    public function coerce_bool_to_string(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));
        $this->assertSame('1', $result);

        $resultFalse = $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true));
        $this->assertSame('', $resultFalse);
    }

    #[Test]
    public function coerce_float_to_string(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('3.14', $result);
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
        $this->assertSame('value', $result['extra']);
    }

    #[Test]
    public function coerce_nullable_value_with_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, strict: false, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_nullable_value_without_nullable_as_type(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, strict: false, nullableAsType: false));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_union_type_with_non_string_item(): void
    {
        $schema = new Schema(type: [42, 'string']);

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function coerce_strict_integer_with_float_string(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('3.14', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_strict_number_with_non_numeric_string(): void
    {
        $schema = new Schema(type: 'number');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('abc', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_non_array_to_object_returns_original(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_to_object_preserves_additional_properties_request(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
        );

        $input = ['id' => '42', 'name' => 'Alice', 'email' => 'a@b.c'];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result['id']);
        $this->assertSame('Alice', $result['name']);
        $this->assertSame('a@b.c', $result['email']);
    }

    #[Test]
    public function coerce_to_object_preserves_null_property_value_request(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer', nullable: true),
            ],
        );

        $result = $this->coercer->coerce(
            ['age' => null],
            new CoercionContext(schema: $schema, enabled: true, nullableAsType: true),
        );

        $this->assertArrayHasKey('age', $result);
        $this->assertNull($result['age']);
    }

    #[Test]
    public function coerce_integer_to_string(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(123, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('123', $result);
    }

    #[Test]
    public function coerce_array_to_string_returns_array_unchanged(): void
    {
        $schema = new Schema(type: 'string');
        $value = ['foo', 'bar'];

        $result = $this->coercer->coerce($value, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(['foo', 'bar'], $result);
    }

    #[Test]
    public function coerce_float_string_to_number_in_strict_mode_succeeds(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('3.14', new CoercionContext(schema: $schema, enabled: true, strict: true));

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_four_level_nested_object(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'l1' => new Schema(
                    type: 'object',
                    properties: [
                        'l2' => new Schema(
                            type: 'object',
                            properties: [
                                'l3' => new Schema(
                                    type: 'object',
                                    properties: [
                                        'l4' => new Schema(type: 'integer'),
                                    ],
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        );
        $input = ['l1' => ['l2' => ['l3' => ['l4' => '99']]]];

        $result = $this->coercer->coerce($input, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(99, $result['l1']['l2']['l3']['l4']);
    }

    #[Test]
    public function coerce_union_type_selects_first_matching_integer(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_null_with_nullable_and_nullable_as_type_false_proceeds_to_coercion(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, strict: false, nullableAsType: false));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_non_convertible_array_to_integer_returns_original(): void
    {
        $schema = new Schema(type: 'integer');
        $value = ['foo', 'bar'];

        $result = $this->coercer->coerce($value, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(['foo', 'bar'], $result);
    }

    #[Test]
    public function coerce_unknown_string_to_boolean_returns_cast(): void
    {
        $schema = new Schema(type: 'boolean');

        $result = $this->coercer->coerce('maybe', new CoercionContext(schema: $schema, enabled: true));

        $this->assertTrue($result);
    }

    #[Test]
    public function coerce_unknown_type_returns_value_unchanged(): void
    {
        $schema = new Schema(type: 'custom');

        $result = $this->coercer->coerce('anything', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('anything', $result);
    }
}
