<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Enum;

use Duyler\OpenApi\Validator\Response\Enum\BooleanCoercionValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BooleanCoercionValue::class)]
final class BooleanCoercionValueTest extends TestCase
{
    #[Test]
    public function cases_contain_all_coercion_values(): void
    {
        $values = array_map(
            static fn(BooleanCoercionValue $value): string => $value->value,
            BooleanCoercionValue::cases(),
        );

        self::assertSame(
            ['true', '1', 'yes', 'on', 'false', '0', 'no', 'off'],
            $values,
        );
    }

    #[Test]
    public function try_from_returns_case_for_valid_value(): void
    {
        self::assertSame(BooleanCoercionValue::True, BooleanCoercionValue::tryFrom('true'));
        self::assertSame(BooleanCoercionValue::One, BooleanCoercionValue::tryFrom('1'));
        self::assertSame(BooleanCoercionValue::Yes, BooleanCoercionValue::tryFrom('yes'));
        self::assertSame(BooleanCoercionValue::On, BooleanCoercionValue::tryFrom('on'));
        self::assertSame(BooleanCoercionValue::False, BooleanCoercionValue::tryFrom('false'));
        self::assertSame(BooleanCoercionValue::Zero, BooleanCoercionValue::tryFrom('0'));
        self::assertSame(BooleanCoercionValue::No, BooleanCoercionValue::tryFrom('no'));
        self::assertSame(BooleanCoercionValue::Off, BooleanCoercionValue::tryFrom('off'));
    }

    #[Test]
    public function try_from_returns_null_for_unknown_value(): void
    {
        self::assertNull(BooleanCoercionValue::tryFrom('maybe'));
        self::assertNull(BooleanCoercionValue::tryFrom(''));
        self::assertNull(BooleanCoercionValue::tryFrom('TRUE'));
    }

    #[Test]
    public function is_truthy_returns_true_for_truthy_values(): void
    {
        self::assertTrue(BooleanCoercionValue::True->isTruthy());
        self::assertTrue(BooleanCoercionValue::One->isTruthy());
        self::assertTrue(BooleanCoercionValue::Yes->isTruthy());
        self::assertTrue(BooleanCoercionValue::On->isTruthy());
    }

    #[Test]
    public function is_truthy_returns_false_for_falsy_values(): void
    {
        self::assertFalse(BooleanCoercionValue::False->isTruthy());
        self::assertFalse(BooleanCoercionValue::Zero->isTruthy());
        self::assertFalse(BooleanCoercionValue::No->isTruthy());
        self::assertFalse(BooleanCoercionValue::Off->isTruthy());
    }
}
