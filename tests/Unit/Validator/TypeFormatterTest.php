<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\TypeFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function fopen;

use const PHP_INT_MAX;

#[CoversClass(TypeFormatter::class)]
final class TypeFormatterTest extends TestCase
{
    #[Test]
    public function returns_int_for_integer_value(): void
    {
        $result = TypeFormatter::format(42);

        self::assertSame('int', $result);
    }

    #[Test]
    public function returns_int_for_max_integer_value(): void
    {
        $result = TypeFormatter::format(PHP_INT_MAX);

        self::assertSame('int', $result);
    }

    #[Test]
    public function returns_float_for_double_value(): void
    {
        $result = TypeFormatter::format(1.5);

        self::assertSame('float', $result);
    }

    #[Test]
    public function returns_string_for_string_value(): void
    {
        $result = TypeFormatter::format('hello');

        self::assertSame('string', $result);
    }

    #[Test]
    public function returns_bool_for_true(): void
    {
        $result = TypeFormatter::format(true);

        self::assertSame('bool', $result);
    }

    #[Test]
    public function returns_bool_for_false(): void
    {
        $result = TypeFormatter::format(false);

        self::assertSame('bool', $result);
    }

    #[Test]
    public function returns_null_for_null_value(): void
    {
        $result = TypeFormatter::format(null);

        self::assertSame('null', $result);
    }

    #[Test]
    public function returns_array_for_list(): void
    {
        $result = TypeFormatter::format([1, 2, 3]);

        self::assertSame('array', $result);
    }

    #[Test]
    public function returns_array_for_assoc(): void
    {
        $result = TypeFormatter::format(['a' => 1, 'b' => 2]);

        self::assertSame('array', $result);
    }

    #[Test]
    public function returns_object_for_stdClass(): void
    {
        $result = TypeFormatter::format(new stdClass());

        self::assertSame('object', $result);
    }

    #[Test]
    public function returns_resource_for_file_handle(): void
    {
        $handle = fopen('php://memory', 'r');

        try {
            $result = TypeFormatter::format($handle);

            self::assertSame('resource', $result);
        } finally {
            fclose($handle);
        }
    }

    #[Test]
    public function does_not_emit_deprecated_double_label_for_float(): void
    {
        $result = TypeFormatter::format(3.14);

        self::assertNotSame('double', $result);
    }

    #[Test]
    public function does_not_emit_deprecated_integer_label_for_int(): void
    {
        $result = TypeFormatter::format(7);

        self::assertNotSame('integer', $result);
    }

    #[Test]
    public function does_not_emit_deprecated_null_label_for_null(): void
    {
        $result = TypeFormatter::format(null);

        self::assertNotSame('NULL', $result);
    }
}
