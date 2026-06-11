<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnresolvableRefExceptionTest extends TestCase
{
    #[Test]
    public function to_string_returns_message(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Unknown',
            reason: 'Schema not found',
        );

        self::assertSame(
            'Cannot resolve $ref "#/components/schemas/Unknown": Schema not found',
            (string) $exception,
        );
    }

    #[Test]
    public function get_ref_returns_ref(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Missing',
            reason: 'Not found',
        );

        self::assertSame('#/components/schemas/Missing', $exception->ref);
    }

    #[Test]
    public function get_reason_returns_reason(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Unknown',
            reason: 'Circular reference detected',
        );

        self::assertSame('Circular reference detected', $exception->reason);
    }
}
