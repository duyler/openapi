<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Response\Internal\NdJsonParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function implode;
use function str_repeat;

#[CoversClass(NdJsonParser::class)]
final class NdJsonParserTest extends TestCase
{
    private NdJsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NdJsonParser();
    }

    #[Test]
    public function parse_string_decodes_two_json_objects(): void
    {
        $result = $this->parser->parseString('{"id":1}' . "\n" . '{"id":2}');

        self::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    #[Test]
    public function parse_string_skips_blank_lines_without_counting(): void
    {
        $result = $this->parser->parseString("{\"a\":1}\n\n\n{\"b\":2}\n");

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_string_yields_null_and_logs_under_non_strict_mode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('NDJSON'),
                $this->callback(static fn(array $ctx): bool => isset($ctx['line']) && isset($ctx['exception'])),
            );

        $parser = new NdJsonParser($logger);

        $result = $parser->parseString('{"ok":true}' . "\n" . 'not-json');

        self::assertSame([['ok' => true], null], $result);
    }

    #[Test]
    public function parse_string_throws_under_strict_mode(): void
    {
        $parser = new NdJsonParser(strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseString('{"ok":true}' . "\n" . 'not-json');
    }

    #[Test]
    public function parse_string_strips_utf8_bom(): void
    {
        $result = $this->parser->parseString("\xEF\xBB\xBF{\"id\":1}\n{\"id\":2}");

        self::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    #[Test]
    public function parse_string_supports_crlf_line_endings(): void
    {
        $result = $this->parser->parseString("{\"a\":1}\r\n{\"b\":2}");

        self::assertSame([['a' => 1], ['b' => 2]], $result);
    }

    #[Test]
    public function parse_string_enforces_max_records(): void
    {
        $parser = new NdJsonParser(maxRecords: 2);

        $this->expectException(TooManyRecordsException::class);

        $parser->parseString('{"a":1}' . "\n" . '{"a":2}' . "\n" . '{"a":3}');
    }

    #[Test]
    public function parse_string_accepts_count_at_boundary(): void
    {
        $parser = new NdJsonParser(maxRecords: 2);

        $result = $parser->parseString('{"a":1}' . "\n" . '{"a":2}');

        self::assertCount(2, $result);
    }

    #[Test]
    public function parse_stream_decodes_chunked_input(): void
    {
        $body = "{\"id\":1,\"name\":\"Alice\"}\n{\"id\":2,\"name\":\"Bob\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertSame([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']], $result);
    }

    #[Test]
    public function parse_stream_matches_parse_string(): void
    {
        $body = "{\"id\":1}\n{\"id\":2}\n{\"id\":3}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        self::assertSame($this->parser->parseString($body), $this->parser->parseStream($stream));
    }

    #[Test]
    public function parse_stream_strips_bom_from_first_chunk(): void
    {
        $body = "\xEF\xBB\xBF{\"ok\":true}\n{\"ok\":false}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertSame([['ok' => true], ['ok' => false]], $result);
    }

    #[Test]
    public function parse_stream_enforces_max_records(): void
    {
        $parser = new NdJsonParser(maxRecords: 2);

        $factory = new Psr17Factory();
        $stream = $factory->createStream('{"a":1}' . "\n" . '{"a":2}' . "\n" . '{"a":3}');

        $this->expectException(TooManyRecordsException::class);

        $parser->parseStream($stream);
    }

    #[Test]
    public function parse_stream_handles_100_records_chunked_across_buffers(): void
    {
        $lines = [];
        for ($i = 0; $i < 100; ++$i) {
            $lines[] = '{"id":' . $i . ',"name":"item-' . str_repeat('x', 100) . '"}';
        }
        $body = implode("\n", $lines);

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream);

        self::assertCount(100, $result);
        self::assertSame(['id' => 0, 'name' => 'item-' . str_repeat('x', 100)], $result[0]);
        self::assertSame(['id' => 99, 'name' => 'item-' . str_repeat('x', 100)], $result[99]);
    }

    #[Test]
    public function parse_string_returns_empty_array_for_empty_body(): void
    {
        self::assertSame([], $this->parser->parseString(''));
    }
}
