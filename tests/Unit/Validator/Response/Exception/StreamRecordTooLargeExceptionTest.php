<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Exception;

use Duyler\OpenApi\Validator\Response\Exception\StreamRecordTooLargeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function strlen;

#[CoversClass(StreamRecordTooLargeException::class)]
final class StreamRecordTooLargeExceptionTest extends TestCase
{
    #[Test]
    public function is_runtime_exception(): void
    {
        $exception = StreamRecordTooLargeException::forStreamLine(1024);

        self::assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function for_stream_line_carries_limit_and_canonical_message(): void
    {
        $exception = StreamRecordTooLargeException::forStreamLine(1_048_576);

        self::assertSame(1_048_576, $exception->maxBytes);
        self::assertSame(
            'Stream line exceeds maximum allowed length of 1048576 bytes',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function for_json_sequence_record_carries_limit_and_canonical_message(): void
    {
        $exception = StreamRecordTooLargeException::forJsonSequenceRecord(10_485_760);

        self::assertSame(10_485_760, $exception->maxBytes);
        self::assertSame(
            'JSON sequence record exceeds maximum allowed length of 10485760 bytes',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function to_string_exposes_message_only_without_stack_trace(): void
    {
        $exception = StreamRecordTooLargeException::forStreamLine(16);

        $rendered = (string) $exception;

        self::assertSame($exception->getMessage(), $rendered);
        self::assertStringNotContainsString(__FILE__, $rendered);
        self::assertStringNotContainsString('StreamRecordTooLargeExceptionTest', $rendered);
        self::assertLessThanOrEqual(strlen($exception->getMessage()) + 1, strlen($rendered));
    }
}
