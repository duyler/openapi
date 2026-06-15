<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

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

    #[Test]
    public function sse_retry_field_with_numeric_value_is_preserved(): void
    {
        $body = "retry: 5000\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame(5000, $result[0]['retry']);
    }

    #[Test]
    public function sse_retry_field_with_non_numeric_value_is_ignored(): void
    {
        $body = "retry: abc\n\n";

        $caught = null;
        $result = null;
        try {
            $result = $this->parser->parseServerSentEvents($body);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNull($caught);
        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]);
        $this->assertArrayNotHasKey('retry', $result[0]);
    }

    #[Test]
    public function sse_retry_field_preserved_with_event_and_data(): void
    {
        $body = "retry: 5000\nevent: update\ndata: {\"v\":1}\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('update', $result[0]['event']);
        $this->assertSame(['v' => 1], $result[0]['data']);
        $this->assertSame(5000, $result[0]['retry']);
    }

    #[Test]
    public function sse_retry_field_with_zero_value_is_preserved(): void
    {
        $body = "retry: 0\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['retry']);
    }

    #[Test]
    public function sse_multiple_retry_fields_last_value_wins(): void
    {
        $body = "retry: 1000\nretry: 5000\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame(5000, $result[0]['retry']);
    }

    #[Test]
    public function sse_retry_only_event_has_no_default_message(): void
    {
        $body = "retry: 5000\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame(5000, $result[0]['retry']);
        $this->assertArrayNotHasKey('event', $result[0]);
    }

    #[Test]
    public function sse_event_field_without_data_still_produces_event(): void
    {
        $body = "event: update\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('update', $result[0]['event']);
        $this->assertArrayNotHasKey('data', $result[0]);
    }

    #[Test]
    public function sse_event_field_with_data_includes_both_keys(): void
    {
        $body = "event: update\ndata: payload\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('update', $result[0]['event']);
        $this->assertSame('payload', $result[0]['data']);
    }

    #[Test]
    public function sse_data_field_without_event_uses_w3c_default_message(): void
    {
        $body = "data: {\"msg\":\"hello\"}\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame(['msg' => 'hello'], $result[0]['data']);
        $this->assertSame('message', $result[0]['event']);
    }

    #[Test]
    public function sse_explicit_event_message_with_data_produces_event_message_key(): void
    {
        $body = "event: message\ndata: {\"msg\":\"hello\"}\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('message', $result[0]['event']);
        $this->assertSame(['msg' => 'hello'], $result[0]['data']);
    }

    #[Test]
    public function sse_empty_event_block_produces_no_events(): void
    {
        $body = "\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function sse_leading_blank_lines_then_event_produces_single_event(): void
    {
        $body = "\n\ndata: payload\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('payload', $result[0]['data']);
    }

    #[Test]
    public function sse_comments_only_stream_produces_empty_result(): void
    {
        $body = ": this is a comment\n\n: another comment\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function sse_comments_mixed_with_events_keeps_only_events(): void
    {
        $body = ": comment one\nevent: keep\ndata: payload\n\n: comment two\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        $this->assertCount(1, $result);
        $this->assertSame('keep', $result[0]['event']);
        $this->assertSame('payload', $result[0]['data']);
    }

    #[Test]
    public function parse_stream_ndjson_1000_lines_memory_growth_under_five_mebibytes(): void
    {
        $lines = [];
        for ($i = 0; $i < 1000; $i++) {
            $lines[] = sprintf('{"id":%d,"name":"item-%d","value":%d}', $i, $i, $i * 10);
        }
        $body = implode("\n", $lines);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        gc_collect_cycles();
        $before = memory_get_usage(false);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        gc_collect_cycles();
        $after = memory_get_usage(false);

        $growth = $after - $before;

        $this->assertCount(1000, $result);
        $this->assertSame(['id' => 0, 'name' => 'item-0', 'value' => 0], $result[0]);
        $this->assertSame(['id' => 999, 'name' => 'item-999', 'value' => 9990], $result[999]);
        $this->assertLessThan(5_242_880, $growth);
    }

    #[Test]
    public function parse_stream_empty_ndjson_has_near_zero_memory_growth(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream('');

        gc_collect_cycles();
        $before = memory_get_usage(false);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        gc_collect_cycles();
        $after = memory_get_usage(false);

        $growth = $after - $before;

        $this->assertSame([], $result);
        $this->assertLessThan(1024, $growth);
    }
}
