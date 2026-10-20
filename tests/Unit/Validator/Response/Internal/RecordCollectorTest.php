<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Response\Internal\RecordCollector;
use Duyler\OpenApi\Validator\Response\StreamingFormat;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RecordCollector::class)]
final class RecordCollectorTest extends TestCase
{
    #[Test]
    public function append_collects_records_in_order(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq);

        $collector->append(['id' => 1]);
        $collector->append(null);
        $collector->append(['id' => 2]);

        self::assertSame([['id' => 1], null, ['id' => 2]], $collector->records());
    }

    #[Test]
    public function append_stores_scalar_json_values(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq);

        $collector->append(42);
        $collector->append('text');
        $collector->append(true);
        $collector->append(1.5);

        self::assertSame([42, 'text', true, 1.5], $collector->records());
    }

    #[Test]
    public function append_throws_when_count_exceeds_max_records(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq, maxRecords: 2);

        $collector->append(['id' => 1]);
        $collector->append(['id' => 2]);

        $this->expectException(TooManyRecordsException::class);
        $this->expectExceptionMessage('exceeds maximum record count: 2');

        $collector->append(['id' => 3]);
    }

    #[Test]
    public function append_accepts_count_at_exact_max_records(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq, maxRecords: 2);

        $collector->append(['id' => 1]);
        $collector->append(['id' => 2]);

        self::assertCount(2, $collector->records());
    }

    #[Test]
    public function append_decoded_stores_decoded_payload(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq);

        $collector->appendDecoded('{"id":1}');

        self::assertSame([['id' => 1]], $collector->records());
    }

    #[Test]
    public function append_decoded_stores_scalar_json_payloads(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq);

        $collector->appendDecoded('42');
        $collector->appendDecoded('"text"');
        $collector->appendDecoded('true');
        $collector->appendDecoded('1.5');

        self::assertSame([42, 'text', true, 1.5], $collector->records());
    }

    #[Test]
    public function append_decoded_skips_blank_payload_without_counting(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::JsonSeq, maxRecords: 1);

        $collector->appendDecoded('   ');
        $collector->appendDecoded("\n\t");

        self::assertSame([], $collector->records());
    }

    #[Test]
    public function append_decoded_logs_ndjson_message_for_ndjson_format(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('JSON line in NDJSON stream'),
                $this->callback(static fn(array $context): bool => isset($context['line'], $context['exception'])),
            );

        $collector = new RecordCollector(format: StreamingFormat::NdJson, logger: $logger);

        $collector->appendDecoded('not-json');
    }

    #[Test]
    public function append_decoded_appends_null_and_logs_original_payload_on_malformed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('sequence item'),
                $this->callback(static function (array $context): bool {
                    return isset($context['json'], $context['exception'])
                        && '  not-json  ' === $context['json'];
                }),
            );

        $collector = new RecordCollector(format: StreamingFormat::JsonSeq, logger: $logger);

        $collector->appendDecoded('  not-json  ');

        self::assertSame([null], $collector->records());
    }

    #[Test]
    public function append_decoded_throws_under_strict_mode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $collector = new RecordCollector(format: StreamingFormat::JsonSeq, logger: $logger, strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $collector->appendDecoded('not-json');
    }

    #[Test]
    public function report_malformed_logs_sse_message_and_original_payload_under_non_strict_mode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('SSE event data'),
                $this->callback(static function (array $context): bool {
                    return isset($context['data'], $context['exception'])
                        && '{invalid' === $context['data'];
                }),
            );

        $collector = new RecordCollector(format: StreamingFormat::Sse, logger: $logger);

        $collector->reportMalformed('{invalid', new JsonException('decoding failed'));
    }

    #[Test]
    public function report_malformed_throws_under_strict_mode(): void
    {
        $collector = new RecordCollector(format: StreamingFormat::Sse, strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $collector->reportMalformed('{invalid', new JsonException('decoding failed'));
    }
}
