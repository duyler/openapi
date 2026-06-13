<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractCoercerTest extends TestCase
{
    private ConcreteCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ConcreteCoercer();
    }

    #[Test]
    public function is_valid_type_returns_true_for_string(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType('hello', 'string'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_string(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType(123, 'string'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_number_float(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(3.14, 'number'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_number_int(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(42, 'number'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_number(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType('42', 'number'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_integer(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(42, 'integer'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_float_as_integer(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType(3.14, 'integer'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_boolean(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(true, 'boolean'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_null(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(null, 'null'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_null(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType('null', 'null'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_object_array(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(['key' => 'value'], 'object'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_array(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType([1, 2, 3], 'array'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_unknown_type(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType('anything', 'custom'));
    }

    #[Test]
    public function coerce_to_boolean_returns_bool_unchanged(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(true));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(false));
    }

    #[Test]
    public function coerce_to_boolean_converts_truthy_strings(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('true'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('1'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('yes'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('on'));
    }

    #[Test]
    public function coerce_to_boolean_converts_falsy_strings(): void
    {
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('false'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('0'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('no'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('off'));
    }

    #[Test]
    public function coerce_to_boolean_converts_int(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(1));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(0));
    }

    #[Test]
    public function coerce_to_boolean_converts_float(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(1.5));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(0.0));
    }

    #[Test]
    public function coerce_to_boolean_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToBoolean([]));
    }

    #[Test]
    public function coerce_to_integer_returns_int_unchanged(): void
    {
        $this->assertSame(42, $this->coercer->exposedCoerceToInteger(42));
    }

    #[Test]
    public function coerce_to_integer_converts_numeric_string(): void
    {
        $this->assertSame(42, $this->coercer->exposedCoerceToInteger('42'));
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_non_numeric_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToInteger('abc', true);
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_float_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToInteger('3.14', true);
    }

    #[Test]
    public function coerce_to_integer_converts_float(): void
    {
        $this->assertSame(3, $this->coercer->exposedCoerceToInteger(3.14));
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_float(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToInteger(3.14, true);
    }

    #[Test]
    public function coerce_to_integer_converts_bool(): void
    {
        $this->assertSame(1, $this->coercer->exposedCoerceToInteger(true));
        $this->assertSame(0, $this->coercer->exposedCoerceToInteger(false));
    }

    #[Test]
    public function coerce_to_integer_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToInteger([]));
    }

    #[Test]
    public function coerce_to_number_returns_float_unchanged(): void
    {
        $this->assertSame(3.14, $this->coercer->exposedCoerceToNumber(3.14));
    }

    #[Test]
    public function coerce_to_number_converts_int_to_float(): void
    {
        $this->assertSame(42.0, $this->coercer->exposedCoerceToNumber(42));
    }

    #[Test]
    public function coerce_to_number_converts_string(): void
    {
        $this->assertSame(3.14, $this->coercer->exposedCoerceToNumber('3.14'));
    }

    #[Test]
    public function coerce_to_number_throws_on_strict_non_numeric(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToNumber('abc', true);
    }

    #[Test]
    public function coerce_to_number_converts_bool(): void
    {
        $this->assertSame(1.0, $this->coercer->exposedCoerceToNumber(true));
        $this->assertSame(0.0, $this->coercer->exposedCoerceToNumber(false));
    }

    #[Test]
    public function coerce_to_number_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToNumber([]));
    }

    #[Test]
    public function coerce_to_string_returns_string_unchanged(): void
    {
        $this->assertSame('hello', $this->coercer->exposedCoerceToString('hello'));
    }

    #[Test]
    public function coerce_to_string_converts_int(): void
    {
        $this->assertSame('42', $this->coercer->exposedCoerceToString(42));
    }

    #[Test]
    public function coerce_to_string_converts_float(): void
    {
        $this->assertSame('3.14', $this->coercer->exposedCoerceToString(3.14));
    }

    #[Test]
    public function coerce_to_string_converts_bool(): void
    {
        $this->assertSame('1', $this->coercer->exposedCoerceToString(true));
        $this->assertSame('', $this->coercer->exposedCoerceToString(false));
    }

    #[Test]
    public function coerce_to_string_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToString([]));
    }
}

final readonly class ConcreteCoercer extends AbstractCoercer
{
    public function exposedIsValidType(mixed $value, string $type): bool
    {
        return $this->isValidType($value, $type);
    }

    public function exposedCoerceToBoolean(mixed $value): mixed
    {
        return $this->coerceToBoolean($value);
    }

    public function exposedCoerceToInteger(mixed $value, bool $strict = false): mixed
    {
        return $this->coerceToInteger($value, $strict);
    }

    public function exposedCoerceToNumber(mixed $value, bool $strict = false): mixed
    {
        return $this->coerceToNumber($value, $strict);
    }

    public function exposedCoerceToString(mixed $value): mixed
    {
        return $this->coerceToString($value);
    }
}
