<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'Schema not found',
            (string) $exception,
        );
    }

    #[Test]
    public function to_string_does_not_leak_external_ref(): void
    {
        $exception = new UnresolvableRefException(
            ref: 'file:///etc/passwd',
            reason: 'External ref denied',
        );

        self::assertSame('External ref denied', (string) $exception);
    }

    #[Test]
    public function ref_not_in_message_for_external_path(): void
    {
        $exception = new UnresolvableRefException(
            ref: 'file:///etc/passwd',
            reason: 'External ref denied',
        );

        self::assertSame('External ref denied', $exception->getMessage());
    }

    #[Test]
    #[DataProvider('provideRefs')]
    public function ref_accessible_via_property_for_trusted_callers(string $ref): void
    {
        $exception = new UnresolvableRefException(
            ref: $ref,
            reason: 'Reason',
        );

        self::assertSame($ref, $exception->ref);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideRefs(): array
    {
        return [
            'internal pointer' => ['#/components/schemas/Missing'],
            'external file path' => ['file:///etc/passwd'],
        ];
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
