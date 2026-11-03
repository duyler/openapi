<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\Numeric\IntegerRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

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
    #[DataProvider('float_value_provider')]
    public function float_value_validated_per_canonical_rules(IntegerRangeValidator $validator, float $value, ?string $expectedMessage): void
    {
        $caught = null;

        try {
            $validator->validate($value);
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        if (null === $expectedMessage) {
            self::assertNull($caught, sprintf('Value %s must be accepted, got: %s', var_export($value, true), null === $caught ? '' : $caught->getMessage()));

            return;
        }

        self::assertNotNull($caught, sprintf('Value %s must be rejected', var_export($value, true)));
        self::assertStringContainsString($expectedMessage, $caught->getMessage());
    }

    public static function float_value_provider(): array
    {
        $int32 = static fn(): IntegerRangeValidator => new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);
        $int64 = static fn(): IntegerRangeValidator => new IntegerRangeValidator('int64', PHP_INT_MIN, PHP_INT_MAX);

        return [
            'integer float 2.0e0 accepted by int32' => [$int32(), 2.0e0, null],
            'integer float in int64 range accepted' => [$int64(), 2.0e0, null],
            'non-integer float 1.5 rejected as not integer by int32' => [$int32(), 1.5, 'requires an integer value'],
            'integer float 3e9 out of int32 range' => [$int32(), 3.0e9, 'Integer out of int32 range'],
            'integer float 2.0e9 within int32 range accepted' => [$int32(), 2.0e9, null],
            'integer float 1e19 out of int64 range (beyond PHP_INT)' => [$int64(), 1.0e19, 'Integer out of int64 range'],
            'integer float PHP_INT_MAX within int64 range' => [$int64(), (float) PHP_INT_MAX, null],
        ];
    }

    #[Test]
    public function int32_and_int64_registered_under_number_domain(): void
    {
        $registry = BuiltinFormats::create();

        $int32 = $registry->getValidator('number', 'int32');
        $int64 = $registry->getValidator('number', 'int64');

        self::assertInstanceOf(IntegerRangeValidator::class, $int32);
        self::assertInstanceOf(IntegerRangeValidator::class, $int64);

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Integer out of int32 range');

        $int32->validate(3.0e9);
    }

    #[Test]
    public function int32_registration_under_integer_domain_is_preserved(): void
    {
        $registry = BuiltinFormats::create();

        self::assertInstanceOf(IntegerRangeValidator::class, $registry->getValidator('integer', 'int32'));
        self::assertInstanceOf(IntegerRangeValidator::class, $registry->getValidator('integer', 'int64'));
    }

    #[Test]
    public function int32_validator_skips_non_scalar_and_null_data(): void
    {
        $validator = new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647);

        $this->expectNotToPerformAssertions();
        $validator->validate('not an integer');
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
