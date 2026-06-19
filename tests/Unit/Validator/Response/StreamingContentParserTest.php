<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

use ReflectionClass;

use function count;

#[CoversClass(StreamingContentParser::class)]
final class StreamingContentParserTest extends TestCase
{
    private StreamingContentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StreamingContentParser();
    }

    #[Test]
    public function parse_json_lines(): void
    {
        $body = "{\"id\":1,\"name\":\"Alice\"}\n{\"id\":2,\"name\":\"Bob\"}";

        $result = $this->parser->parse($body, 'application/jsonl');

        self::assertCount(2, $result);
        self::assertSame(['id' => 1, 'name' => 'Alice'], $result[0]);
        self::assertSame(['id' => 2, 'name' => 'Bob'], $result[1]);
    }

    #[Test]
    public function parse_json_lines_with_invalid_line(): void
    {
        $body = "{\"valid\":true}\ninvalid json\n{\"also\":\"valid\"}";

        $result = $this->parser->parse($body, 'application/x-ndjson');

        self::assertCount(3, $result);
        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
        self::assertSame(['also' => 'valid'], $result[2]);
    }

    #[Test]
    public function parse_server_sent_events(): void
    {
        $body = "event: message\ndata: {\"text\":\"hello\"}\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('message', $result[0]['event']);
        self::assertSame(['text' => 'hello'], $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_multiple(): void
    {
        $body = "event: message\ndata: first\n\nevent: message\ndata: second\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(2, $result);
        self::assertSame('first', $result[0]['data']);
        self::assertSame('second', $result[1]['data']);
    }

    #[Test]
    public function parse_server_sent_events_with_id(): void
    {
        $body = "id: 123\nevent: update\ndata: test\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertSame('123', $result[0]['id']);
        self::assertSame('update', $result[0]['event']);
    }

    #[Test]
    public function parse_server_sent_events_ignores_comments(): void
    {
        $body = ": this is a comment\nevent: test\ndata: value\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('this is a comment', $result[0]);
    }

    #[Test]
    public function parse_json_sequence(): void
    {
        $body = "\x1E{\"id\":1}\x1E{\"id\":2}\x1E";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(2, $result);
        self::assertSame(['id' => 1], $result[0]);
        self::assertSame(['id' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_sequence_with_invalid(): void
    {
        $body = "\x1E{\"valid\":true}\x1Einvalid\x1E{\"also\":\"valid\"}\x1E";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(3, $result);
        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
        self::assertSame(['also' => 'valid'], $result[2]);
    }

    #[Test]
    public function parse_empty_body(): void
    {
        $result = $this->parser->parse('', 'application/jsonl');

        self::assertSame([], $result);
    }

    #[Test]
    public function parse_unknown_content_type(): void
    {
        $result = $this->parser->parse('some data', 'text/plain');

        self::assertSame([], $result);
    }

    #[Test]
    public function parse_json_lines_empty_lines_ignored(): void
    {
        $body = "{\"a\":1}\n\n\n{\"b\":2}\n";

        $result = $this->parser->parse($body, 'application/jsonl');

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_json_lines_x_ndjson_content_type(): void
    {
        $body = "{\"item\":1}\n{\"item\":2}";

        $result = $this->parser->parse($body, 'application/x-ndjson');

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_server_sent_events_with_plaintext_data(): void
    {
        $body = "data: plain text message\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('plain text message', $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_empty_lines_between_events(): void
    {
        $body = "data: first\n\n\ndata: second\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(2, $result);
        self::assertSame('first', $result[0]['data']);
        self::assertSame('second', $result[1]['data']);
    }

    #[Test]
    public function parse_json_sequence_without_trailing_separator(): void
    {
        $body = "\x1E{\"a\":1}\x1E{\"b\":2}";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_sequence_empty_items_skipped(): void
    {
        $body = "\x1E\x1E{\"valid\":true}\x1E\x1E";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(1, $result);
        self::assertSame(['valid' => true], $result[0]);
    }

    #[Test]
    public function parse_server_sent_events_with_multiple_data_fields(): void
    {
        $body = "data: line1\ndata: line2\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('line1' . "\n" . 'line2', $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_with_three_data_fields(): void
    {
        $body = "data: line1\ndata: line2\ndata: line3\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('line1' . "\n" . 'line2' . "\n" . 'line3', $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_with_empty_data_field(): void
    {
        $body = "data:\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('', $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_empty_data_then_non_empty(): void
    {
        $body = "data:\ndata: hello\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame('hello', $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_non_empty_data_then_empty(): void
    {
        $body = "data: line1\ndata:\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertSame("line1\n", $result[0]['data']);
    }

    #[Test]
    public function parse_server_sent_events_without_data_field(): void
    {
        $body = "event: notification\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('data', $result[0]);
        self::assertSame('notification', $result[0]['event']);
    }

    #[Test]
    public function parse_server_sent_events_non_data_fields_overwrite(): void
    {
        $body = "event: first\nevent: second\nid: 1\nid: 2\ndata: value\n\n";

        $result = $this->parser->parse($body, 'text/event-stream');

        self::assertSame('second', $result[0]['event']);
        self::assertSame('2', $result[0]['id']);
        self::assertSame('value', $result[0]['data']);
    }

    #[Test]
    public function parse_stream_sse_multi_line_data_matches_parse(): void
    {
        $body = "data: line1\ndata: line2\ndata: line3\n\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
        self::assertSame('line1' . "\n" . 'line2' . "\n" . 'line3', $result[0]['data']);
    }

    #[Test]
    public function parse_json_lines_with_charset_in_content_type(): void
    {
        $body = "{\"test\":1}";

        $result = $this->parser->parse($body, 'application/jsonl; charset=utf-8');

        self::assertCount(1, $result);
        self::assertSame(['test' => 1], $result[0]);
    }

    #[Test]
    public function parse_json_lines_direct_call(): void
    {
        $body = "{\"direct\":1}\n{\"direct\":2}";

        $result = $this->parser->parseJsonLines($body);

        self::assertCount(2, $result);
        self::assertSame(['direct' => 1], $result[0]);
        self::assertSame(['direct' => 2], $result[1]);
    }

    #[Test]
    public function parse_server_sent_events_direct_call(): void
    {
        $body = "event: test\ndata: value\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('test', $result[0]['event']);
        self::assertSame('value', $result[0]['data']);
    }

    #[Test]
    public function parse_json_seq_direct_call(): void
    {
        $body = "\x1E{\"direct\":1}\x1E{\"direct\":2}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(2, $result);
        self::assertSame(['direct' => 1], $result[0]);
        self::assertSame(['direct' => 2], $result[1]);
    }

    #[Test]
    public function logs_warning_on_invalid_json_line_in_ndjson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('NDJSON'),
                $this->callback(function (array $context): bool {
                    return isset($context['line']) && isset($context['exception']);
                }),
            );

        $parser = new StreamingContentParser($logger);

        $parser->parseJsonLines('{"valid":true}' . "\n" . 'not-json');
    }

    #[Test]
    public function logs_warning_on_invalid_json_sequence_item(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('JSON sequence'),
                $this->callback(function (array $context): bool {
                    return isset($context['json']) && isset($context['exception']);
                }),
            );

        $parser = new StreamingContentParser($logger);

        $parser->parseJsonSeq("\x1E{\"valid\":true}\x1Enot-json");
    }

    #[Test]
    public function logs_warning_on_invalid_sse_event_data(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('SSE event data'),
                $this->callback(function (array $context): bool {
                    return isset($context['data']) && isset($context['exception']);
                }),
            );

        $parser = new StreamingContentParser($logger);

        $body = "event: test\ndata: {invalid json}\n\n";

        $result = $parser->parseServerSentEvents($body);

        self::assertSame('{invalid json}', $result[0]['data']);
    }

    #[Test]
    public function uses_null_logger_by_default(): void
    {
        $parser = new StreamingContentParser();

        $body = "{\"valid\":true}\nnot-json\n{\"also\":\"valid\"}";

        $result = $parser->parseJsonLines($body);

        self::assertCount(3, $result);
        self::assertNull($result[1]);
    }

    #[Test]
    public function logs_each_invalid_line_in_ndjson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $parser = new StreamingContentParser($logger);

        $parser->parseJsonLines("{\"ok\":1}\nbad1\nbad2\n{\"ok\":2}");
    }

    #[Test]
    public function does_not_log_on_valid_json_lines(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger);

        $parser->parseJsonLines("{\"a\":1}\n{\"b\":2}");
    }

    #[Test]
    public function does_not_log_on_valid_json_sequence(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger);

        $parser->parseJsonSeq("\x1E{\"a\":1}\x1E{\"b\":2}");
    }

    #[Test]
    public function does_not_log_on_valid_sse_event_data(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $parser = new StreamingContentParser($logger);

        $parser->parseServerSentEvents("event: test\ndata: {\"key\":\"value\"}\n\n");
    }

    #[Test]
    public function parse_json_lines_with_crlf_line_endings(): void
    {
        $body = "{\"a\":1}\r\n{\"b\":2}";

        $result = $this->parser->parseJsonLines($body);

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_lines_with_mixed_line_endings(): void
    {
        $body = "{\"a\":1}\r\n{\"b\":2}\n{\"c\":3}";

        $result = $this->parser->parseJsonLines($body);

        self::assertCount(3, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
        self::assertSame(['c' => 3], $result[2]);
    }

    #[Test]
    public function parse_json_lines_crlf_via_parse_method(): void
    {
        $body = "{\"a\":1}\r\n{\"b\":2}";

        $result = $this->parser->parse($body, 'application/jsonl');

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_lines_crlf_with_invalid_line(): void
    {
        $body = "{\"valid\":true}\r\ninvalid json\r\n{\"also\":\"valid\"}";

        $result = $this->parser->parse($body, 'application/x-ndjson');

        self::assertCount(3, $result);
        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
        self::assertSame(['also' => 'valid'], $result[2]);
    }

    #[Test]
    public function parse_json_seq_without_leading_record_separator(): void
    {
        $body = "{\"a\":1}\x1E{\"b\":2}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_seq_without_leading_rs_via_parse_method(): void
    {
        $body = "{\"a\":1}\x1E{\"b\":2}";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(2, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_seq_without_leading_rs_with_invalid(): void
    {
        $body = "{\"valid\":true}\x1Einvalid\x1E{\"also\":\"valid\"}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(3, $result);
        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
        self::assertSame(['also' => 'valid'], $result[2]);
    }

    #[Test]
    public function parse_stream_jsonl_matches_parse(): void
    {
        $body = "{\"id\":1,\"name\":\"Alice\"}\n{\"id\":2,\"name\":\"Bob\"}\n{\"id\":3,\"name\":\"Charlie\"}";

        $expected = $this->parser->parse($body, 'application/jsonl');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertSame($expected, $result);
        self::assertCount(3, $result);
    }

    #[Test]
    public function parse_stream_ndjson_matches_parse(): void
    {
        $body = '{"name":"item1","count":10}' . "\n" . '{"name":"item2","count":20}';

        $expected = $this->parser->parse($body, 'application/x-ndjson');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parse_stream_sse_matches_parse(): void
    {
        $body = "event: message\n"
            . "data: {\"message\":\"hello\",\"count\":1}\n\n"
            . "event: update\n"
            . "data: {\"message\":\"world\",\"count\":2}\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parse_stream_sse_with_id_matches_parse(): void
    {
        $body = "id: 123\nevent: message\ndata: {\"message\":\"test\",\"count\":1}\n\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parse_stream_sse_ignores_comments(): void
    {
        $body = ": this is a comment\nevent: message\ndata: {\"message\":\"hello\",\"count\":1}\n\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parse_stream_json_seq_matches_parse(): void
    {
        $body = "\x1E{\"id\":\"1\",\"value\":\"first\"}\x1E{\"id\":\"2\",\"value\":\"second\"}";

        $expected = $this->parser->parse($body, 'application/json-seq');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/json-seq');

        self::assertSame($expected, $result);
    }

    #[Test]
    public function parse_stream_jsonl_empty_body(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream('');

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertSame([], $result);
    }

    #[Test]
    public function parse_stream_jsonl_empty_lines_ignored(): void
    {
        $body = "{\"valid\":true}\n\n{\"also\":\"valid\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_stream_jsonl_with_invalid_line(): void
    {
        $body = "{\"valid\":true}\ninvalid json\n{\"also\":\"valid\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertCount(3, $result);
        self::assertSame(['valid' => true], $result[0]);
        self::assertNull($result[1]);
        self::assertSame(['also' => 'valid'], $result[2]);
    }

    #[Test]
    public function parse_stream_non_streaming_type_returns_empty(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream('{"foo":"bar"}');

        $result = $this->parser->parseStream($stream, 'application/json');

        self::assertSame([], $result);
    }

    #[Test]
    public function parse_stream_large_ndjson_across_chunks(): void
    {
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = '{"id":' . $i . ',"name":"item-' . str_repeat('x', 100) . '"}';
        }
        $body = implode("\n", $items);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertCount(100, $result);
        self::assertSame(['id' => 0, 'name' => 'item-' . str_repeat('x', 100)], $result[0]);
        self::assertSame(['id' => 99, 'name' => 'item-' . str_repeat('x', 100)], $result[99]);
    }

    #[Test]
    public function parse_stream_jsonl_throws_on_line_exceeding_max_length(): void
    {
        $body = str_repeat('x', 1_048_577);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds maximum allowed length');

        $this->parser->parseStream($stream, 'application/jsonl');
    }

    #[Test]
    public function parse_stream_sse_throws_on_line_exceeding_max_length(): void
    {
        $body = 'data: ' . str_repeat('x', 1_048_577);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds maximum allowed length');

        $this->parser->parseStream($stream, 'text/event-stream');
    }

    #[Test]
    public function parse_stream_json_seq_throws_on_record_exceeding_custom_max_record_length(): void
    {
        $body = str_repeat('x', 1025);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $parser = new StreamingContentParser(maxRecordLength: 1024);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON sequence record exceeds maximum allowed length of 1024 bytes');

        $parser->parseStream($stream, 'application/json-seq');
    }

    #[Test]
    public function parse_stream_json_seq_allows_record_at_default_max_record_length(): void
    {
        $record = str_repeat('x', 5 * 1024 * 1024); // 5 MB
        $body = "\x1E" . $record . "\x1E";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/json-seq');

        self::assertCount(1, $result);
    }

    #[Test]
    public function default_constructor_limits(): void
    {
        $reflection = new ReflectionClass($this->parser);

        $maxLineLength = $reflection->getProperty('maxLineLength')->getValue($this->parser);
        $maxRecordLength = $reflection->getProperty('maxRecordLength')->getValue($this->parser);

        self::assertSame(1_048_576, $maxLineLength);
        self::assertSame(10_485_760, $maxRecordLength);
    }

    #[Test]
    public function parse_json_lines_strips_utf8_bom(): void
    {
        $body = "\xEF\xBB\xBF{\"id\":1}\n{\"id\":2}";

        $result = $this->parser->parseJsonLines($body);

        self::assertCount(2, $result);
        self::assertSame(['id' => 1], $result[0]);
        self::assertSame(['id' => 2], $result[1]);
    }

    #[Test]
    public function parse_server_sent_events_strips_utf8_bom(): void
    {
        $body = "\xEF\xBB\xBFevent: msg\ndata: {}\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('msg', $result[0]['event']);
        self::assertSame([], $result[0]['data']);
    }

    #[Test]
    public function parse_json_seq_strips_utf8_bom(): void
    {
        $body = "\xEF\xBB\xBF\x1E{\"id\":1}\x1E{\"id\":2}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(2, $result);
        self::assertSame(['id' => 1], $result[0]);
        self::assertSame(['id' => 2], $result[1]);
    }

    #[Test]
    public function parse_json_lines_without_bom_keeps_behavior(): void
    {
        $body = "{\"id\":1}\n{\"id\":2}";

        $result = $this->parser->parseJsonLines($body);

        self::assertCount(2, $result);
        self::assertSame(['id' => 1], $result[0]);
        self::assertSame(['id' => 2], $result[1]);
    }

    #[Test]
    public function parse_stream_jsonl_allows_line_at_exact_max_length(): void
    {
        $body = str_repeat('x', 1_048_576);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertCount(1, $result);
        self::assertNull($result[0]);
    }

    /**
     * ST-12: JSON-seq (RFC 7464) with optional newline after record separator.
     *
     * RFC 7464 §2.1: "The remainder of the record [...] MAY be terminated by
     * a line feed (U+000A)." This verifies the parser handles both forms.
     */
    #[Test]
    public function parse_json_sequence_with_newline_after_record_separator(): void
    {
        $body = "\x1E\n{\"id\":\"1\"}\x1E\n{\"id\":\"2\"}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(2, $result);
        self::assertSame(['id' => '1'], $result[0]);
        self::assertSame(['id' => '2'], $result[1]);
    }

    /**
     * ST-12: JSON-seq with newline after RS via parse() dispatcher.
     */
    #[Test]
    public function parse_json_sequence_with_newline_after_rs_via_parse_method(): void
    {
        $body = "\x1E\n{\"id\":\"1\"}\x1E\n{\"id\":\"2\"}";

        $result = $this->parser->parse($body, 'application/json-seq');

        self::assertCount(2, $result);
        self::assertSame(['id' => '1'], $result[0]);
        self::assertSame(['id' => '2'], $result[1]);
    }

    /**
     * ST-12: Behaviour parity — JSON-seq with newline must produce the same
     * item count as JSON-seq without newline.
     */
    #[Test]
    public function parse_json_sequence_newline_and_no_newline_produce_same_count(): void
    {
        $bodyWithNewline = "\x1E\n{\"id\":\"1\"}\x1E\n{\"id\":\"2\"}\x1E\n{\"id\":\"3\"}";
        $bodyWithoutNewline = "\x1E{\"id\":\"1\"}\x1E{\"id\":\"2\"}\x1E{\"id\":\"3\"}";

        $withNewline = $this->parser->parseJsonSeq($bodyWithNewline);
        $withoutNewline = $this->parser->parseJsonSeq($bodyWithoutNewline);

        self::assertSame(count($withoutNewline), count($withNewline));
        self::assertCount(3, $withNewline);
        self::assertSame($withoutNewline, $withNewline);
    }

    /**
     * ST-12: Mixed form — some records have a trailing newline, others don't.
     * RFC 7464 allows both forms within the same sequence.
     */
    #[Test]
    public function parse_json_sequence_mixed_newline_and_no_newline(): void
    {
        $body = "\x1E\n{\"a\":1}\x1E{\"b\":2}\x1E\n{\"c\":3}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(3, $result);
        self::assertSame(['a' => 1], $result[0]);
        self::assertSame(['b' => 2], $result[1]);
        self::assertSame(['c' => 3], $result[2]);
    }

    /**
     * ST-12: CRLF after RS is also valid per RFC 7464 (newline can be \r\n).
     */
    #[Test]
    public function parse_json_sequence_with_crlf_after_record_separator(): void
    {
        $body = "\x1E\r\n{\"id\":\"1\"}\x1E\r\n{\"id\":\"2\"}";

        $result = $this->parser->parseJsonSeq($body);

        self::assertCount(2, $result);
        self::assertSame(['id' => '1'], $result[0]);
        self::assertSame(['id' => '2'], $result[1]);
    }

    /**
     * ST-12: Stream-based parser must also accept newline after RS.
     */
    #[Test]
    public function parse_stream_json_sequence_with_newline_after_rs_matches_parse(): void
    {
        $body = "\x1E\n{\"id\":\"1\",\"value\":\"first\"}\x1E\n{\"id\":\"2\",\"value\":\"second\"}";

        $expected = $this->parser->parse($body, 'application/json-seq');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/json-seq');

        self::assertSame($expected, $result);
        self::assertCount(2, $result);
        self::assertSame(['id' => '1', 'value' => 'first'], $result[0]);
        self::assertSame(['id' => '2', 'value' => 'second'], $result[1]);
    }

    /**
     * EI-037: WHATWG HTML SSE §8.1.1 requires CRLF, LF, and CR line endings.
     */
    #[Test]
    public function parse_server_sent_events_with_crlf_line_endings(): void
    {
        $body = "event: message\r\ndata: {\"msg\":\"hello\"}\r\n\r\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('message', $result[0]['event']);
        self::assertSame(['msg' => 'hello'], $result[0]['data']);
    }

    /**
     * EI-037: Legacy CR-only line endings must also be supported.
     */
    #[Test]
    public function parse_server_sent_events_with_cr_only_line_endings(): void
    {
        $body = "event: message\rdata: hello\r\r";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('message', $result[0]['event']);
        self::assertSame('hello', $result[0]['data']);
    }

    /**
     * EI-037: Mixed line endings within a single body.
     */
    #[Test]
    public function parse_server_sent_events_with_mixed_line_endings(): void
    {
        $body = "event: first\r\ndata: one\n\nevent: second\rdata: two\r\r";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(2, $result);
        self::assertSame('first', $result[0]['event']);
        self::assertSame('one', $result[0]['data']);
        self::assertSame('second', $result[1]['event']);
        self::assertSame('two', $result[1]['data']);
    }

    /**
     * EI-038: WHATWG HTML SSE §8.2.6 removes exactly one leading U+0020 SPACE.
     * Multiple leading spaces preserve all but the first. Plaintext value is
     * used to avoid JSON-decode masking the space preservation.
     */
    #[Test]
    public function parse_server_sent_events_preserves_extra_leading_space_in_value(): void
    {
        $body = "data:   indented text\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('  indented text', $result[0]['data']);
    }

    /**
     * EI-038: Value without a leading space is left untouched.
     */
    #[Test]
    public function parse_server_sent_events_value_without_leading_space_unchanged(): void
    {
        $body = "data:value\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('value', $result[0]['data']);
    }

    /**
     * EI-038: A single leading space on each accumulated data line is removed,
     * but subsequent spaces inside the value survive the multi-line join.
     */
    #[Test]
    public function parse_server_sent_events_multi_line_with_extra_spaces(): void
    {
        $body = "data:  first\ndata:  second\n\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame(" first\n second", $result[0]['data']);
    }

    /**
     * EI-037: Multi-line data-field accumulation must survive CRLF endings.
     */
    #[Test]
    public function parse_server_sent_events_crlf_multi_line_data_accumulation(): void
    {
        $body = "data: line1\r\ndata: line2\r\n\r\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame("line1\nline2", $result[0]['data']);
    }

    /**
     * EI-037: Comment line with CRLF endings is ignored.
     */
    #[Test]
    public function parse_server_sent_events_crlf_comment_line_ignored(): void
    {
        $body = ": this is a comment\r\nevent: test\r\ndata: value\r\n\r\n";

        $result = $this->parser->parseServerSentEvents($body);

        self::assertCount(1, $result);
        self::assertSame('test', $result[0]['event']);
        self::assertSame('value', $result[0]['data']);
    }

    /**
     * EI-037 + EI-038: Stream-based parser must match the string-based parser
     * for CRLF endings and single-space removal.
     */
    #[Test]
    public function parse_stream_sse_crlf_matches_parse(): void
    {
        $body = "event: message\r\ndata: {\"msg\":\"hello\"}\r\n\r\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
        self::assertCount(1, $result);
        self::assertSame(['msg' => 'hello'], $result[0]['data']);
    }

    /**
     * EI-037: Stream-based parser must support CR-only endings.
     */
    #[Test]
    public function parse_stream_sse_cr_only_matches_parse(): void
    {
        $body = "event: message\rdata: hello\r\r";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
        self::assertSame('hello', $result[0]['data']);
    }

    /**
     * EI-038: Stream-based parser must remove exactly one leading space.
     * Plaintext value avoids JSON-decode masking the space preservation.
     */
    #[Test]
    public function parse_stream_sse_preserves_extra_leading_space_in_value(): void
    {
        $body = "data:   indented text\n\n";

        $expected = $this->parser->parse($body, 'text/event-stream');

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        self::assertSame($expected, $result);
        self::assertSame('  indented text', $result[0]['data']);
    }
}
