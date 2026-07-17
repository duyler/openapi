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

    /**
     * P-010: `\stdClass` is normalized to its public-property view via
     * get_object_vars(), matching TypeCoercer::normalizeValue. This lets
     * consumers using `json_decode` without the associative flag (or plain
     * object casts) reach the validator without manual conversion.
     */
    #[Test]
    public function normalizes_stdClass_to_array(): void
    {
        $input = new stdClass();
        $input->a = 1;
        $input->b = [1, 2];

        $result = SchemaValueNormalizer::normalize($input);

        self::assertSame(['a' => 1, 'b' => [1, 2]], $result);
    }

    #[Test]
    public function normalizes_empty_stdClass_to_empty_array(): void
    {
        $result = SchemaValueNormalizer::normalize(new stdClass());

        self::assertSame([], $result);
    }

    /**
     * Documents the top-level-only limitation: nested stdClass instances
     * inside the normalized array are NOT recursively converted. Consumers
     * needing deep conversion must call normalize themselves.
     */
    #[Test]
    public function normalizes_stdClass_only_at_top_level(): void
    {
        $inner = new stdClass();
        $inner->inner = 1;

        $outer = new stdClass();
        $outer->outer = $inner;

        $result = SchemaValueNormalizer::normalize($outer);

        self::assertSame(['outer' => $inner], $result);
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
        $this->expectExceptionMessageMatches('/Data must be array, int, string, float or bool, object/');

        SchemaValueNormalizer::normalize(fn() => true);
    }

    #[Test]
    public function normalize_datetime_throws_exception(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessageMatches('/Data must be array, int, string, float or bool, object/');

        SchemaValueNormalizer::normalize(new DateTime());
    }

    #[Test]
    public function type_includes_null_returns_false_for_string_type(): void
    {
        $type = 'string';

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertFalse($result);
    }

    #[Test]
    public function type_includes_null_returns_false_for_null_type(): void
    {
        $type = null;

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertFalse($result);
    }

    #[Test]
    public function type_includes_null_returns_false_for_array_without_null(): void
    {
        $type = ['string', 'integer'];

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertFalse($result);
    }

    #[Test]
    public function type_includes_null_returns_true_for_array_with_string_null(): void
    {
        $type = ['string', 'null'];

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertTrue($result);
    }

    #[Test]
    public function type_includes_null_returns_true_for_array_with_php_null(): void
    {
        $type = ['string', null];

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertTrue($result);
    }

    #[Test]
    public function type_includes_null_returns_true_for_array_with_only_null(): void
    {
        $type = ['null'];

        $result = SchemaValueNormalizer::typeIncludesNull($type);

        self::assertTrue($result);
    }
}
