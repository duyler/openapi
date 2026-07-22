<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\Numeric\IntegerRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(IntegerRangeValidator::class)]
final class IntegerRangeValidatorTest extends TestCase
{
    #[Test]
    public function int32_validator_accepts_in_range_boundaries(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $this->expectNotToPerformAssertions();
        $validator->validate(0);
        $validator->validate(2_147_483_647);
        $validator->validate(-2_147_483_648);
        $validator->validate(42);
        $validator->validate(-1);
    }

    #[Test]
    public function int32_validator_rejects_above_max(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Integer out of int32 range [-2147483648, 2147483647]');

        $validator->validate(2_147_483_648);
    }

    #[Test]
    public function int32_validator_rejects_below_min(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Integer out of int32 range [-2147483648, 2147483647]');

        $validator->validate(-2_147_483_649);
    }

    #[Test]
    public function int32_validator_skips_non_int_data(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $this->expectNotToPerformAssertions();
        $validator->validate('not an integer');
        $validator->validate(3.14);
        $validator->validate(null);
        $validator->validate(['array']);
    }

    #[Test]
    public function int32_validator_rejects_large_value_above_int32_max(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $exception = null;

        try {
            $validator->validate(3_000_000_000);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, '3e9 must be rejected by int32 validator');
        $this->assertSame('int32', $exception->format);
    }

    #[Test]
    public function int64_validator_accepts_php_int_max(): void
    {
        $validator = new IntegerRangeValidator('int64', PHP_INT_MIN, PHP_INT_MAX);

        $this->expectNotToPerformAssertions();
        $validator->validate(PHP_INT_MAX);
    }

    #[Test]
    public function int64_validator_accepts_php_int_min(): void
    {
        $validator = new IntegerRangeValidator('int64', PHP_INT_MIN, PHP_INT_MAX);

        $this->expectNotToPerformAssertions();
        $validator->validate(PHP_INT_MIN);
    }

    #[Test]
    public function int64_validator_accepts_typical_id_values(): void
    {
        $validator = new IntegerRangeValidator('int64', PHP_INT_MIN, PHP_INT_MAX);

        $this->expectNotToPerformAssertions();
        $validator->validate(1234567890);
        $validator->validate(9_223_372_036_854_775_807);
        $validator->validate(-9_223_372_036_854_775_808);
    }

    #[Test]
    public function int32_validator_does_not_disclose_attacker_value_in_message(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);
        $attackerValue = 9_999_999_999;

        $exception = null;

        try {
            $validator->validate($attackerValue);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertStringNotContainsString((string) $attackerValue, $exception->getMessage());
    }
}
