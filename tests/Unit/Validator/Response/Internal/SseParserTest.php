<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Response\Internal\SseParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SseParser::class)]
final class SseParserTest extends TestCase
{
    private SseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SseParser();
    }

    #[Test]
    public function parse_string_decodes_single_event_with_json_data(): void
    {
        $result = $this->parser->parseString("event: message\ndata: {\"text\":\"hello\"}\n\n");

        self::assertCount(1, $result);
        self::assertSame('message', $result[0]['event']);
        self::assertSame(['text' => 'hello'], $result[0]['data']);
    }

    #[Test]
    public function parse_string_decodes_multiple_events(): void
    {
        $body = "event: message\ndata: first\n\nevent: message\ndata: second\n\n";

        $result = $this->parser->parseString($body);

        self::assertSame('first', $result[0]['data']);
        self::assertSame('second', $result[1]['data']);
    }

    #[Test]
    public function parse_string_ignores_comment_lines(): void
    {
        $result = $this->parser->parseString(": this is a comment\nevent: test\ndata: value\n\n");

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('this is a comment', $result[0]);
    }

    #[Test]
    public function parse_string_assigns_w3c_default_message_event_when_data_only(): void
    {
        $result = $this->parser->parseString("data: payload\n\n");

        self::assertSame('message', $result[0]['event']);
        self::assertSame('payload', $result[0]['data']);
    }

    #[Test]
    public function parse_string_omits_default_event_when_event_field_present(): void
    {
        $result = $this->parser->parseString("event: update\ndata: value\n\n");

        self::assertSame('update', $result[0]['event']);
    }

    #[Test]
    public function parse_string_accumulates_multiple_data_fields_with_lf_separator(): void
    {
        $result = $this->parser->parseString("data: line1\ndata: line2\ndata: line3\n\n");

        self::assertSame("line1\nline2\nline3", $result[0]['data']);
    }

    #[Test]
    public function parse_string_strips_exactly_one_leading_space_from_data_value(): void
    {
        $result = $this->parser->parseString("data:   indented text\n\n");

        self::assertSame('  indented text', $result[0]['data']);
    }

    #[Test]
    public function parse_string_preserves_value_without_leading_space(): void
    {
        $result = $this->parser->parseString("data:value\n\n");

        self::assertSame('value', $result[0]['data']);
    }

    #[Test]
    public function parse_string_keeps_empty_data_field_as_empty_string(): void
    {
        $result = $this->parser->parseString("data:\n\n");

        self::assertSame('', $result[0]['data']);
    }

    #[Test]
    public function parse_string_concatenates_empty_data_with_subsequent_value(): void
    {
        $result = $this->parser->parseString("data:\ndata: hello\n\n");

        self::assertSame('hello', $result[0]['data']);
    }

    #[Test]
    public function parse_string_preserves_trailing_empty_data_via_lf_concat(): void
    {
        $result = $this->parser->parseString("data: line1\ndata:\n\n");

        self::assertSame("line1\n", $result[0]['data']);
    }

    #[Test]
    public function parse_string_retries_field_as_integer_ms_when_numeric(): void
    {
        $result = $this->parser->parseString("retry: 5000\n\n");

        self::assertSame(5000, $result[0]['retry']);
    }

    #[Test]
    public function parse_string_drops_retry_field_when_non_numeric(): void
    {
        $result = $this->parser->parseString("retry: abc\n\n");

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('retry', $result[0]);
    }

    #[Test]
    public function parse_string_preserves_id_field(): void
    {
        $result = $this->parser->parseString("id: 123\nevent: update\ndata: test\n\n");

        self::assertSame('123', $result[0]['id']);
    }

    #[Test]
    public function parse_string_flushes_pending_event_without_trailing_blank(): void
    {
        $result = $this->parser->parseString('event: unfinished' . "\ndata: payload-without-final-newline");

        self::assertCount(1, $result);
        self::assertSame('unfinished', $result[0]['event']);
        self::assertSame('payload-without-final-newline', $result[0]['data']);
    }

    #[Test]
    public function parse_string_treats_no_colon_line_as_field_with_empty_value(): void
    {
        $result = $this->parser->parseString("data\n\n");

        self::assertSame('', $result[0]['data']);
        self::assertSame('message', $result[0]['event']);
    }

    #[Test]
    public function parse_string_keeps_plaintext_data_on_invalid_json_under_non_strict(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $parser = new SseParser($logger);

        $result = $parser->parseString("event: test\ndata: {invalid json}\n\n");

        self::assertSame('{invalid json}', $result[0]['data']);
    }

    #[Test]
    public function parse_string_throws_on_invalid_json_data_under_strict_mode(): void
    {
        $parser = new SseParser(strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseString("event: test\ndata: {invalid json}\n\n");
    }

    #[Test]
    public function parse_string_strips_utf8_bom(): void
    {
        $result = $this->parser->parseString("\xEF\xBB\xBFevent: msg\ndata: {}\n\n");

        self::assertCount(1, $result);
        self::assertSame('msg', $result[0]['event']);
        self::assertSame([], $result[0]['data']);
    }

    #[Test]
    public function parse_string_supports_crlf_line_endings(): void
    {
        $result = $this->parser->parseString("event: message\r\ndata: {\"msg\":\"hello\"}\r\n\r\n");

        self::assertSame('message', $result[0]['event']);
        self::assertSame(['msg' => 'hello'], $result[0]['data']);
    }

    #[Test]
    public function parse_string_supports_cr_only_line_endings(): void
    {
        $result = $this->parser->parseString("event: message\rdata: hello\r\r");

        self::assertSame('message', $result[0]['event']);
        self::assertSame('hello', $result[0]['data']);
    }

    #[Test]
    public function parse_string_enforces_max_records_per_event(): void
    {
        $parser = new SseParser(maxRecords: 2);

        $this->expectException(TooManyRecordsException::class);

        $parser->parseString("data: a\n\ndata: b\n\ndata: c\n\n");
    }

    #[Test]
    public function parse_string_counts_events_not_lines_when_multi_line_data(): void
    {
        $parser = new SseParser(maxRecords: 2);

        $body = "event: first\ndata: a\ndata: b\ndata: c\n\n"
            . "event: second\ndata: d\ndata: e\ndata: f\n\n";

        $result = $parser->parseString($body);

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_string_returns_empty_for_empty_body(): void
    {
        self::assertSame([], $this->parser->parseString(''));
    }

    #[Test]
    public function parse_string_returns_empty_for_comments_only(): void
    {
        self::assertSame([], $this->parser->parseString(": comment one\n\n: comment two\n\n"));
    }

    #[Test]
    public function parse_stream_matches_parse_string(): void
    {
        $body = "event: message\n"
            . "data: {\"message\":\"hello\",\"count\":1}\n\n"
            . "event: update\n"
            . "data: {\"message\":\"world\",\"count\":2}\n";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        self::assertSame($this->parser->parseString($body), $this->parser->parseStream($stream));
    }

    #[Test]
    public function parse_stream_strips_bom_from_first_chunk(): void
    {
        $body = "\xEF\xBB\xBF"
            . "event: first\ndata: {\"msg\":\"hello\"}\n\n"
            . "event: second\ndata: {\"msg\":\"world\"}\n\n";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertCount(2, $result);
        self::assertSame('first', $result[0]['event']);
        self::assertSame('second', $result[1]['event']);
    }
}
