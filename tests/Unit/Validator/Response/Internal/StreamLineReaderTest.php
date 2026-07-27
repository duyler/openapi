<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Response\Internal\StreamLineReader;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function iterator_to_array;
use function str_repeat;
use function strlen;

#[CoversClass(StreamLineReader::class)]
final class StreamLineReaderTest extends TestCase
{
    #[Test]
    public function read_lines_yields_each_complete_line_with_default_split(): void
    {
        $reader = new StreamLineReader();

        $factory = new Psr17Factory();
        $stream = $factory->createStream("line1\nline2\nline3");

        $lines = iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);

        self::assertSame(['line1', 'line2', 'line3'], $lines);
    }

    #[Test]
    public function read_lines_strips_utf8_bom_from_first_chunk_only(): void
    {
        $reader = new StreamLineReader();

        $factory = new Psr17Factory();
        $stream = $factory->createStream("\xEF\xBB\xBFfirst\nsecond");

        $lines = iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);

        self::assertSame(['first', 'second'], $lines);
    }

    #[Test]
    public function read_lines_throws_when_buffer_exceeds_max_line_length(): void
    {
        $reader = new StreamLineReader(maxLineLength: 16);

        $factory = new Psr17Factory();
        $stream = $factory->createStream(str_repeat('x', 32));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream line exceeds maximum allowed length of 16 bytes');

        iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);
    }

    #[Test]
    public function read_lines_accepts_buffer_at_exact_limit(): void
    {
        $reader = new StreamLineReader(maxLineLength: 32);

        $factory = new Psr17Factory();
        $stream = $factory->createStream(str_repeat('x', 32));

        $lines = iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);

        self::assertSame([str_repeat('x', 32)], $lines);
    }

    #[Test]
    public function read_lines_supports_crlf_split_pattern(): void
    {
        $reader = new StreamLineReader();

        $factory = new Psr17Factory();
        $stream = $factory->createStream("a\r\nb\r\nc");

        $lines = iterator_to_array($reader->readLines($stream, "/\r\n|\r|\n/"), false);

        self::assertSame(['a', 'b', 'c'], $lines);
    }

    #[Test]
    public function read_lines_returns_empty_buffer_for_empty_stream(): void
    {
        $reader = new StreamLineReader();

        $factory = new Psr17Factory();
        $stream = $factory->createStream('');

        $lines = iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);

        self::assertSame([''], $lines);
    }

    #[Test]
    public function strip_bom_returns_body_unchanged_without_bom_prefix(): void
    {
        $body = '{"id":1}';

        self::assertSame($body, StreamLineReader::stripBom($body));
    }

    #[Test]
    public function strip_bom_removes_three_byte_utf8_bom_prefix(): void
    {
        $body = "\xEF\xBB\xBF{\"id\":1}";

        $result = StreamLineReader::stripBom($body);

        self::assertSame('{"id":1}', $result);
        self::assertSame(strlen('{"id":1}'), strlen($result));
    }
}
