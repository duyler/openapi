<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidMultipleOfSchemaException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(InvalidMultipleOfSchemaException::class)]
final class InvalidMultipleOfSchemaExceptionTest extends TestCase
{
    /**
     * Pins the deprecated BC factory retained since R4-CORRECTNESS-008.
     * NumericRangeValidator no longer throws it; the factory survives only
     * for external callers and will be removed in 2.0. The PHP 8.4
     * #[Deprecated] attribute emits E_DEPRECATED at the call site, which
     * PHPUnit surfaces as deprecation details without failing the test.
     */
    #[Test]
    public function deprecated_factory_for_large_integer_without_bcmath_includes_both_arguments_in_message(): void
    {
        $exception = InvalidMultipleOfSchemaException::forLargeIntegerWithoutBcmath(
            9223372036854775807,
            0.0001,
        );

        self::assertInstanceOf(InvalidMultipleOfSchemaException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('9223372036854775807', $exception->getMessage());
        self::assertStringContainsString('0.0001', $exception->getMessage());
        self::assertStringContainsString('BCMath', $exception->getMessage());
    }
}
