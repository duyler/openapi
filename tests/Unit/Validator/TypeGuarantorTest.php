<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\TypeGuarantor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;
use stdClass;

final class TypeGuarantorTest extends TestCase
{
    #[Test]
    public function ensure_valid_type_returns_array_as_is(): void
    {
        $value = [1, 2, 3];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensure_valid_type_throws_type_error_for_object(): void
    {
        $value = new stdClass();

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('object (stdClass)');

        TypeGuarantor::ensureValidType($value);
    }

    #[Test]
    public function ensure_valid_type_throws_type_error_for_resource(): void
    {
        $resource = fopen('php://memory', 'r');

        try {
            $this->expectException(TypeError::class);
            $this->expectExceptionMessage('resource');

            TypeGuarantor::ensureValidType($resource);
        } finally {
            fclose($resource);
        }
    }

    #[Test]
    public function ensure_valid_type_returns_int_as_is(): void
    {
        $value = 42;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensure_valid_type_returns_string_as_is(): void
    {
        $value = 'test';
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensure_valid_type_returns_float_as_is(): void
    {
        $value = 3.14;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function ensure_valid_type_returns_bool_as_is(): void
    {
        $value = true;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertTrue($result);
    }

    #[Test]
    public function ensure_valid_type_returns_bool_false_as_is(): void
    {
        $value = false;
        $result = TypeGuarantor::ensureValidType($value);

        self::assertFalse($result);
    }

    #[Test]
    public function ensure_valid_type_converts_empty_array_as_is(): void
    {
        $value = [];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function ensure_valid_type_converts_nested_array_as_is(): void
    {
        $value = [['nested' => 'value']];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertSame([['nested' => 'value']], $result);
    }

    #[Test]
    public function ensure_valid_type_converts_assoc_array_as_is(): void
    {
        $value = ['key' => 'value', 'number' => 42];
        $result = TypeGuarantor::ensureValidType($value);

        self::assertIsArray($result);
        self::assertSame(['key' => 'value', 'number' => 42], $result);
    }

    #[Test]
    public function ensure_valid_type_converts_numeric_string_to_string(): void
    {
        $value = '123';
        $result = TypeGuarantor::ensureValidType($value);

        self::assertSame('123', $result);
    }

    #[Test]
    public function ensure_valid_type_returns_null_for_null_input(): void
    {
        $result = TypeGuarantor::ensureValidType(null);

        self::assertNull($result);
    }
}
