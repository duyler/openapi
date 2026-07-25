<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Response\Internal\JsonSeqParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(JsonSeqParser::class)]
final class JsonSeqParserTest extends TestCase
{
    private JsonSeqParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonSeqParser();
    }

    #[Test]
    public function parse_string_decodes_two_records_with_leading_separator(): void
    {
        $result = $this->parser->parseString("\x1E{\"id\":1}\x1E{\"id\":2}");

        self::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    #[Test]
    public function parse_string_handles_missing_leading_separator(): void
    {
        $result = $this->parser->parseString("{\"a\":1}\x1E{\"b\":2}");

        self::assertSame([['a' => 1], ['b' => 2]], $result);
    }

    #[Test]
    public function parse_string_skips_empty_records(): void
    {
        $result = $this->parser->parseString("\x1E\x1E{\"valid\":true}\x1E\x1E");

        self::assertSame([['valid' => true]], $result);
    }

    #[Test]
    public function parse_string_supports_optional_newline_after_separator(): void
    {
        $result = $this->parser->parseString("\x1E\n{\"id\":\"1\"}\x1E\n{\"id\":\"2\"}");

        self::assertSame([['id' => '1'], ['id' => '2']], $result);
    }

    #[Test]
    public function parse_string_supports_crlf_after_separator(): void
    {
        $result = $this->parser->parseString("\x1E\r\n{\"id\":\"1\"}\x1E\r\n{\"id\":\"2\"}");

        self::assertSame([['id' => '1'], ['id' => '2']], $result);
    }

    #[Test]
    public function parse_string_yields_null_under_non_strict_on_malformed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('JSON sequence'),
                $this->callback(static fn(array $ctx): bool => isset($ctx['json']) && isset($ctx['exception'])),
            );

        $parser = new JsonSeqParser($logger);

        $result = $parser->parseString("\x1E{\"valid\":true}\x1Einvalid");

        self::assertSame([['valid' => true], null], $result);
    }

    #[Test]
    public function parse_string_throws_under_strict_mode(): void
    {
        $parser = new JsonSeqParser(strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseString("\x1E{\"ok\":true}\x1Ebroken");
    }

    #[Test]
    public function parse_string_strips_utf8_bom(): void
    {
        $result = $this->parser->parseString("\xEF\xBB\xBF\x1E{\"id\":1}\x1E{\"id\":2}");

        self::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    #[Test]
    public function parse_string_enforces_max_records(): void
    {
        $parser = new JsonSeqParser(maxRecords: 2);

        $this->expectException(TooManyRecordsException::class);

        $parser->parseString("\x1E{\"a\":1}\x1E{\"a\":2}\x1E{\"a\":3}");
    }

    #[Test]
    public function parse_string_returns_empty_for_empty_body(): void
    {
        self::assertSame([], $this->parser->parseString(''));
    }

    #[Test]
    public function parse_stream_decodes_chunked_input(): void
    {
        $body = "\x1E{\"id\":\"1\",\"value\":\"first\"}\x1E{\"id\":\"2\",\"value\":\"second\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertSame([['id' => '1', 'value' => 'first'], ['id' => '2', 'value' => 'second']], $result);
    }

    #[Test]
    public function parse_stream_matches_parse_string(): void
    {
        $body = "\x1E{\"id\":\"1\"}\x1E{\"id\":\"2\"}\x1E\n{\"id\":\"3\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        self::assertSame($this->parser->parseString($body), $this->parser->parseStream($stream));
    }

    #[Test]
    public function parse_stream_strips_bom_from_first_chunk(): void
    {
        $body = "\xEF\xBB\xBF\x1E{\"id\":\"1\"}\x1E{\"id\":\"2\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertSame([['id' => '1'], ['id' => '2']], $result);
    }

    #[Test]
    public function parse_stream_throws_when_record_exceeds_max_record_length(): void
    {
        $oversizeRecord = "\x1E" . str_repeat('c', 1_048_577);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($oversizeRecord);

        $parser = new JsonSeqParser(maxRecordLength: 1_048_576);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON sequence record exceeds maximum allowed length of 1048576 bytes');

        $parser->parseStream($stream);
    }

    #[Test]
    public function parse_stream_enforces_max_records(): void
    {
        $parser = new JsonSeqParser(maxRecords: 2);

        $factory = new Psr17Factory();
        $stream = $factory->createStream("\x1E{\"a\":1}\x1E{\"a\":2}\x1E{\"a\":3}");

        $this->expectException(TooManyRecordsException::class);

        $parser->parseStream($stream);
    }

    #[Test]
    public function parse_stream_supports_newline_after_separator_rfc_7464(): void
    {
        $body = "\x1E\n{\"id\":\"1\",\"value\":\"first\"}\x1E\n{\"id\":\"2\",\"value\":\"second\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertSame([['id' => '1', 'value' => 'first'], ['id' => '2', 'value' => 'second']], $result);
    }
}
