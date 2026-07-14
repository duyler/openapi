<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\JsonEquals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonEquals::class)]
final class JsonEqualsTest extends TestCase
{
    #[Test]
    public function equals_int_and_float_with_same_math_value(): void
    {
        $a = 1;
        $b = 1.0;

        self::assertTrue(JsonEquals::equals($a, $b));
    }

    #[Test]
    public function equals_float_and_int_is_commutative(): void
    {
        $a = 1.0;
        $b = 1;

        self::assertTrue(JsonEquals::equals($a, $b));
    }

    #[Test]
    public function equals_zero_int_and_zero_float(): void
    {
        self::assertTrue(JsonEquals::equals(0, 0.0));
    }

    #[Test]
    public function not_equals_when_numeric_values_differ(): void
    {
        self::assertFalse(JsonEquals::equals(1, 2));
    }

    #[Test]
    public function not_equals_int_and_string_with_same_text(): void
    {
        self::assertFalse(JsonEquals::equals(1, '1'));
    }

    #[Test]
    public function not_equals_number_and_boolean(): void
    {
        self::assertFalse(JsonEquals::equals(1, true));
    }

    #[Test]
    public function equals_strings_with_same_value(): void
    {
        self::assertTrue(JsonEquals::equals('a', 'a'));
    }

    #[Test]
    public function equals_booleans_with_same_value(): void
    {
        self::assertTrue(JsonEquals::equals(true, true));
    }

    #[Test]
    public function equals_null_values(): void
    {
        self::assertTrue(JsonEquals::equals(null, null));
    }

    #[Test]
    public function not_equals_null_and_false(): void
    {
        self::assertFalse(JsonEquals::equals(null, false));
    }

    #[Test]
    public function equals_empty_arrays(): void
    {
        self::assertTrue(JsonEquals::equals([], []));
    }

    #[Test]
    public function equals_identical_arrays(): void
    {
        self::assertTrue(JsonEquals::equals([1, 2, 3], [1, 2, 3]));
    }

    #[Test]
    public function not_equals_arrays_with_different_numeric_representation(): void
    {
        self::assertFalse(JsonEquals::equals([1], [1.0]));
    }
}
