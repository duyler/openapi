<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Validator\TypeGuarantor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TypeGuarantorTest extends TestCase
{
    #[Test]
    public function ensureValidType_returns_array_as_is(): void
    {
        $value = [1, 2, 3];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_array_with_nullable_as_type_true(): void
    {
        $value = ['key' => 'value'];
        $result = TypeGuarantor::ensureValidType($value, true);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_array_with_nullable_as_type_false(): void
    {
        $value = [1, 2, 3];
        $result = TypeGuarantor::ensureValidType($value, false);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_null_when_nullable_as_type_true(): void
    {
        $result = TypeGuarantor::ensureValidType(null, true);

        self::assertNull($result);
    }

    #[Test]
    public function ensureValidType_returns_string_when_null_and_nullable_as_type_false(): void
    {
        $result = TypeGuarantor::ensureValidType(null, false);

        self::assertSame('', $result);
    }

    #[Test]
    public function ensureValidType_returns_int_as_is(): void
    {
        $value = 42;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_int_with_nullable_as_type_true(): void
    {
        $value = 42;
        $result = TypeGuarantor::ensureValidType($value, true);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_int_with_nullable_as_type_false(): void
    {
        $value = 42;
        $result = TypeGuarantor::ensureValidType($value, false);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_string_as_is(): void
    {
        $value = 'test';
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_string_with_nullable_as_type_true(): void
    {
        $value = 'hello';
        $result = TypeGuarantor::ensureValidType($value, true);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_string_with_nullable_as_type_false(): void
    {
        $value = 'world';
        $result = TypeGuarantor::ensureValidType($value, false);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_float_as_is(): void
    {
        $value = 3.14;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_float_with_nullable_as_type_true(): void
    {
        $value = 2.5;
        $result = TypeGuarantor::ensureValidType($value, true);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_float_with_nullable_as_type_false(): void
    {
        $value = 1.75;
        $result = TypeGuarantor::ensureValidType($value, false);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensureValidType_returns_bool_as_is(): void
    {
        $value = true;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertTrue($result);
    }

    #[Test]
    public function ensureValidType_returns_bool_false_as_is(): void
    {
        $value = false;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertFalse($result);
    }

    #[Test]
    public function ensureValidType_returns_bool_with_nullable_as_type_true(): void
    {
        $value = true;
        $result = TypeGuarantor::ensureValidType($value, true);

        self::assertTrue($result);
    }

    #[Test]
    public function ensureValidType_returns_bool_with_nullable_as_type_false(): void
    {
        $value = false;
        $result = TypeGuarantor::ensureValidType($value, false);

        self::assertFalse($result);
    }

    #[Test]
    public function ensureValidType_converts_empty_array_as_is(): void
    {
        $value = [];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function ensureValidType_converts_nested_array_as_is(): void
    {
        $value = [['nested' => 'value']];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertSame([['nested' => 'value']], $result);
    }

    #[Test]
    public function ensureValidType_converts_assoc_array_as_is(): void
    {
        $value = ['key' => 'value', 'number' => 42];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertSame(['key' => 'value', 'number' => 42], $result);
    }

    #[Test]
    public function ensureValidType_converts_numeric_string_to_string(): void
    {
        $value = '123';
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame('123', $result);
    }

    #[Test]
    public function ensureValidType_default_nullable_as_type_is_true(): void
    {
        $result = TypeGuarantor::ensureValidType(null);

        self::assertNull($result);
    }
}
