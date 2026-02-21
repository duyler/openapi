<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        self::assertSame('line2', $result[0]['data']);
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
}
