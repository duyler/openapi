<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamingContentParser::class)]
final class StreamingContentParserBomTest extends TestCase
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    private StreamingContentParser $parser;

    #[Override]
    protected function setUp(): void
    {
        $this->parser = new StreamingContentParser();
    }

    #[Test]
    public function ndjson_stream_with_bom_strips_bom_from_first_chunk(): void
    {
        $body = self::UTF8_BOM . "{\"ok\":true}\n{\"ok\":false}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        $this->assertCount(2, $result);
        $this->assertNotNull($result[0]);
        $this->assertSame(['ok' => true], $result[0]);
        $this->assertNotNull($result[1]);
        $this->assertSame(['ok' => false], $result[1]);
    }

    #[Test]
    public function sse_stream_with_bom_preserves_first_event(): void
    {
        $body = self::UTF8_BOM
            . "event: first\ndata: {\"msg\":\"hello\"}\n\n"
            . "event: second\ndata: {\"msg\":\"world\"}\n\n";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'text/event-stream');

        $this->assertCount(2, $result);
        $this->assertNotNull($result[0]);
        $this->assertSame('first', $result[0]['event']);
        $this->assertSame(['msg' => 'hello'], $result[0]['data']);
        $this->assertNotNull($result[1]);
        $this->assertSame('second', $result[1]['event']);
        $this->assertSame(['msg' => 'world'], $result[1]['data']);
    }

    #[Test]
    public function json_seq_stream_with_bom_preserves_first_record(): void
    {
        $body = self::UTF8_BOM . "\x1E{\"id\":\"1\"}\x1E{\"id\":\"2\"}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/json-seq');

        $this->assertCount(2, $result);
        $this->assertNotNull($result[0]);
        $this->assertSame(['id' => '1'], $result[0]);
        $this->assertNotNull($result[1]);
        $this->assertSame(['id' => '2'], $result[1]);
    }

    #[Test]
    public function stream_without_bom_unchanged(): void
    {
        $body = "{\"first\":1}\n{\"second\":2}";

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $result = $this->parser->parseStream($stream, 'application/x-ndjson');

        $this->assertCount(2, $result);
        $this->assertNotNull($result[0]);
        $this->assertSame(['first' => 1], $result[0]);
        $this->assertNotNull($result[1]);
        $this->assertSame(['second' => 2], $result[1]);
    }
}
