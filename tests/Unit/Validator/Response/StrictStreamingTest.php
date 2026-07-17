<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(StreamingContentParser::class)]
final class StrictStreamingTest extends TestCase
{
    #[Test]
    public function strict_mode_throws_on_malformed_record(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger, strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseJsonLines('{"valid":true}' . "\n" . 'not-json');
    }

    #[Test]
    public function default_mode_logs_and_skips_malformed_record(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $parser = new StreamingContentParser($logger);

        $result = $parser->parseJsonLines('{"valid":true}' . "\n" . 'not-json');

        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
    }

    #[Test]
    public function strict_mode_throws_on_malformed_json_seq_item(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger, strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseJsonSeq("\x1E{\"ok\":true}\x1Ebroken");
    }

    #[Test]
    public function strict_mode_throws_on_malformed_sse_data(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger, strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseServerSentEvents("event: test\ndata: {invalid json}\n\n");
    }

    #[Test]
    public function default_mode_keeps_raw_value_on_malformed_sse_data(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $parser = new StreamingContentParser($logger);

        $result = $parser->parseServerSentEvents("event: test\ndata: {invalid json}\n\n");

        self::assertSame('{invalid json}', $result[0]['data']);
    }
}
