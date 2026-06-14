<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StreamingContentParser::class)]
final class StreamingContentParserEdgeCaseTest extends TestCase
{
    private StreamingContentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StreamingContentParser();
    }

    #[Test]
    public function sse_line_without_field_colon_format_is_silently_ignored(): void
    {
        $body = "data: keep\n"
            . "no-colon-line-that-is-ignored\n"
            . "data: concatenated\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame("keep\nconcatenated", $result[0]['data']);
    }

    #[Test]
    public function ndjson_truncated_without_trailing_newline_returns_last_item(): void
    {
        $body = "{\"first\":1}\n{\"last\":\"untagged\"}";

        $result = $this->parser->parseJsonLines($body);

        $this->assertCount(2, $result);
        $this->assertSame(['first' => 1], $result[0]);
        $this->assertSame(['last' => 'untagged'], $result[1]);
    }

    #[Test]
    public function sse_truncated_without_terminator_returns_pending_event(): void
    {
        $body = "event: unfinished\ndata: payload-without-final-newline";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('unfinished', $result[0]['event']);
        $this->assertSame('payload-without-final-newline', $result[0]['data']);
    }

    #[Test]
    public function sse_truncated_stream_without_terminator_returns_pending_event(): void
    {
        $body = "event: tail\ndata: stream-no-terminator";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        $this->assertCount(1, $result);
        $this->assertSame('tail', $result[0]['event']);
        $this->assertSame('stream-no-terminator', $result[0]['data']);
    }

    #[Test]
    public function sse_empty_body_returns_empty_event_list(): void
    {
        $result = $this->parser->parseServerSentEvents('');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_stream_ndjson_throws_runtime_exception_when_line_exceeds_one_mebibyte(): void
    {
        $oversizeLine = str_repeat('a', 1_048_577);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($oversizeLine);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream line exceeds maximum allowed length of 1048576 bytes');

        $this->parser->parseStream($stream, 'application/x-ndjson');
    }

    #[Test]
    public function parse_stream_ndjson_accepts_valid_line_at_exact_one_mebibyte_limit(): void
    {
        $payload = '{"id":' . str_repeat('1', 1_048_576 - 8) . '}';

        $factory = new Psr17Factory();
        $stream = $factory->createStream($payload);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
    }

    #[Test]
    public function parse_stream_sse_throws_runtime_exception_when_line_exceeds_one_mebibyte(): void
    {
        $oversizeLine = 'data: ' . str_repeat('b', 1_048_572);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($oversizeLine);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream line exceeds maximum allowed length of 1048576 bytes');

        $this->parser->parseStream($stream, 'text/event-stream');
    }

    #[Test]
    public function parse_stream_json_seq_throws_runtime_exception_when_record_exceeds_one_mebibyte(): void
    {
        $oversizeRecord = "\x1E" . str_repeat('c', 1_048_577);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($oversizeRecord);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream line exceeds maximum allowed length of 1048576 bytes');

        $this->parser->parseStream($stream, 'application/json-seq');
    }
}
