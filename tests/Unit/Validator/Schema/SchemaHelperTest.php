<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
final class SchemaHelperTest extends TestCase
{
    #[Test]
    public function normalize_array_returns_same_array(): void
    {
        $input = ['foo' => 'bar'];
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_empty_array_returns_same_array(): void
    {
        $input = [];
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_int_returns_same_int(): void
    {
        $input = 42;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_negative_int_returns_same_int(): void
    {
        $input = -123;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_string_returns_same_string(): void
    {
        $input = 'hello world';
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_empty_string_returns_same_string(): void
    {
        $input = '';
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_float_returns_same_float(): void
    {
        $input = 3.14;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_negative_float_returns_same_float(): void
    {
        $input = -2.5;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function normalize_true_returns_same_bool(): void
    {
        $input = true;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertTrue($result);
    }

    #[Test]
    public function normalize_false_returns_same_bool(): void
    {
        $input = false;
        $result = SchemaValueNormalizer::normalize($input);

        self::assertFalse($result);
    }

    #[Test]
    public function normalize_null_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Data must be array, int, string, float or bool, null given');

        SchemaValueNormalizer::normalize(null);
    }

    #[Test]
    public function normalize_object_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Data must be array, int, string, float or bool, stdClass given');

        SchemaValueNormalizer::normalize(new stdClass());
    }

    #[Test]
    public function normalize_resource_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessageMatches('/Data must be array, int, string, float or bool, resource/');

        $handle = fopen('php://memory', 'r');
        try {
            SchemaValueNormalizer::normalize($handle);
        } finally {
            fclose($handle);
        }
    }

    #[Test]
    public function normalize_callable_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessageMatches('/Data must be array, int, string, float or bool, Closure/');

        SchemaValueNormalizer::normalize(fn() => true);
    }

    #[Test]
    public function normalize_datetime_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessageMatches('/Data must be array, int, string, float or bool, DateTime/');

        SchemaValueNormalizer::normalize(new DateTime());
    }
}
