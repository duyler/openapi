<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Response\Exception\StreamRecordTooLargeException;
use Duyler\OpenApi\Validator\Response\Internal\JsonSeqParser;
use Duyler\OpenApi\Validator\Response\Internal\NdJsonParser;
use Duyler\OpenApi\Validator\Response\Internal\SseParser;
use Duyler\OpenApi\Validator\Response\Internal\StreamLineReader;
use Duyler\OpenApi\Validator\Response\Internal\StreamingFormatParser;
use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Duyler\OpenApi\Validator\Response\StreamingMediaTypeDetector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function iterator_to_array;
use function min;
use function str_repeat;
use function strlen;
use function substr;

use const SEEK_SET;

#[CoversClass(StreamingContentParser::class)]
#[CoversClass(StreamingMediaTypeDetector::class)]
final class StreamingFormatRoutingTest extends TestCase
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    private const int STUB_STREAM_CHUNK_LIMIT = 7;

    private StreamingContentParser $parser;

    #[Override]
    protected function setUp(): void
    {
        $this->parser = new StreamingContentParser();
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: class-string<StreamingFormatParser>|null}>
     */
    public static function provide_detection_vectors(): array
    {
        return [
            'jsonl is streaming ndjson' => ['application/jsonl', true, NdJsonParser::class],
            'x-ndjson is streaming ndjson' => ['application/x-ndjson', true, NdJsonParser::class],
            'json-seq is streaming jsonseq' => ['application/json-seq', true, JsonSeqParser::class],
            'vendor suffixed json-seq is not streaming' => ['application/vnd.foo+json-seq', false, null],
            'event-stream is streaming sse' => ['text/event-stream', true, SseParser::class],
            'regular json is not streaming' => ['application/json', false, null],
            'ndjson with charset parameter routes ndjson' => ['application/x-ndjson; charset=utf-8', true, NdJsonParser::class],
        ];
    }

    #[Test]
    #[DataProvider('provide_detection_vectors')]
    public function detector_and_parser_for_agree_on_vector(
        string $contentType,
        bool $expectedStreaming,
        ?string $expectedParserClass,
    ): void {
        self::assertSame($expectedStreaming, StreamingMediaTypeDetector::isStreaming($contentType));
        self::assertSame($expectedStreaming, StreamingMediaTypeDetector::isStreaming($contentType . '; q=1'));

        $resolved = $this->parser->parserFor($contentType);

        if (null === $expectedParserClass) {
            self::assertNull($resolved);

            return;
        }

        self::assertInstanceOf($expectedParserClass, $resolved);
    }

    #[Test]
    #[DataProvider('provide_detection_vectors')]
    public function parse_and_parse_stream_match_resolved_parser_for_vector(
        string $contentType,
        bool $expectedStreaming,
        ?string $expectedParserClass,
    ): void {
        $body = '{"id":1}';

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        $resolved = $this->parser->parserFor($contentType);

        if (false === $expectedStreaming) {
            self::assertSame([], $this->parser->parse($body, $contentType));
            self::assertSame([], $this->parser->parseStream($stream, $contentType));

            return;
        }

        self::assertNotNull($resolved);
        self::assertSame($resolved->parseString($body), $this->parser->parse($body, $contentType));
        self::assertSame($resolved->parseStream($factory->createStream($body)), $this->parser->parseStream($stream, $contentType));
    }

    #[Test]
    public function sequential_bom_parse_streams_strip_bom_per_pass(): void
    {
        $parser = new StreamingContentParser();
        $factory = new Psr17Factory();

        $first = $parser->parseStream(
            $factory->createStream(self::UTF8_BOM . '{"id":1}'),
            'application/x-ndjson',
        );
        $second = $parser->parseStream(
            $factory->createStream(self::UTF8_BOM . '{"second":2}'),
            'application/x-ndjson',
        );

        self::assertSame([['id' => 1]], $first);
        self::assertSame([['second' => 2]], $second);
    }

    #[Test]
    public function cross_format_bom_streams_strip_bom_per_pass(): void
    {
        $parser = new StreamingContentParser();
        $factory = new Psr17Factory();

        $ndjson = $parser->parseStream(
            $factory->createStream(self::UTF8_BOM . '{"a":1}'),
            'application/x-ndjson',
        );
        $sse = $parser->parseStream(
            $factory->createStream(self::UTF8_BOM . "event: m\ndata: {}\n\n"),
            'text/event-stream',
        );
        $jsonSeq = $parser->parseStream(
            $factory->createStream(self::UTF8_BOM . "\x1E{\"c\":3}"),
            'application/json-seq',
        );

        self::assertSame([['a' => 1]], $ndjson);
        self::assertSame([['event' => 'm', 'data' => []]], $sse);
        self::assertSame([['c' => 3]], $jsonSeq);
    }

    #[Test]
    public function stream_line_reader_strips_bom_on_each_read_lines_pass(): void
    {
        $reader = new StreamLineReader();
        $factory = new Psr17Factory();

        $first = iterator_to_array(
            $reader->readLines($factory->createStream(self::UTF8_BOM . "a\nb"), "/\r?\n/"),
            false,
        );
        $second = iterator_to_array(
            $reader->readLines($factory->createStream(self::UTF8_BOM . "c\nd"), "/\r?\n/"),
            false,
        );

        self::assertSame(['a', 'b'], $first);
        self::assertSame(['c', 'd'], $second);
    }

    #[Test]
    public function ndjson_scalar_json_values_are_collected(): void
    {
        $body = '42' . "\n" . '"text"' . "\n" . 'true' . "\n" . '1.5';

        self::assertSame([42, 'text', true, 1.5], $this->parser->parseJsonLines($body));
    }

    #[Test]
    public function ndjson_scalar_stream_matches_parse_string(): void
    {
        $body = '42' . "\n" . '"text"' . "\n" . 'true';

        $factory = new Psr17Factory();
        $stream = $factory->createStream($body);

        self::assertSame(
            $this->parser->parseJsonLines($body),
            $this->parser->parseStream($stream, 'application/jsonl'),
        );
    }

    #[Test]
    public function json_seq_scalar_values_are_collected(): void
    {
        $body = "\x1E" . '42' . "\x1E" . '"text"';

        self::assertSame([42, 'text'], $this->parser->parseJsonSeq($body));
    }

    #[Test]
    public function strict_mode_accepts_scalar_json_values(): void
    {
        $parser = new StreamingContentParser(strictStreaming: true);

        self::assertSame([42], $parser->parseJsonLines('42'));
    }

    #[Test]
    public function line_length_violation_surfaces_domain_exception(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream(str_repeat('x', 1_048_577));

        $this->expectException(StreamRecordTooLargeException::class);
        $this->expectExceptionMessage('Stream line exceeds maximum allowed length of 1048576 bytes');

        $this->parser->parseStream($stream, 'application/x-ndjson');
    }

    #[Test]
    public function record_length_violation_surfaces_domain_exception(): void
    {
        $parser = new StreamingContentParser(maxRecordLength: 1_048_576);

        $factory = new Psr17Factory();
        $stream = $factory->createStream("\x1E" . str_repeat('x', 1_048_577));

        $this->expectException(StreamRecordTooLargeException::class);
        $this->expectExceptionMessage('JSON sequence record exceeds maximum allowed length of 1048576 bytes');

        $parser->parseStream($stream, 'application/json-seq');
    }

    #[Test]
    public function stream_line_reader_enforces_buffer_length_across_chunks(): void
    {
        $reader = new StreamLineReader(maxLineLength: 16);
        $stream = $this->createChunkedStream(str_repeat('x', 40), self::STUB_STREAM_CHUNK_LIMIT);

        $this->expectException(StreamRecordTooLargeException::class);

        iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);
    }

    #[Test]
    public function stream_line_reader_assembles_lines_spanning_small_chunks(): void
    {
        $reader = new StreamLineReader();
        $stream = $this->createChunkedStream("alpha\nbeta\ngamma", self::STUB_STREAM_CHUNK_LIMIT);

        $lines = iterator_to_array($reader->readLines($stream, "/\r?\n/"), false);

        self::assertSame(['alpha', 'beta', 'gamma'], $lines);
    }

    #[Test]
    public function validator_configuration_default_streaming_records_cap_is_pinned(): void
    {
        self::assertSame(100_000, ValidatorConfiguration::DEFAULT_MAX_STREAMING_RECORDS);
    }

    #[Test]
    public function aborted_chunk_iteration_does_not_leak_state_between_passes(): void
    {
        $reader = new StreamLineReader();
        $abortedStream = $this->createChunkedStream(
            self::UTF8_BOM . '{"a":1}' . "\n" . '{"b":2}',
            self::STUB_STREAM_CHUNK_LIMIT,
        );

        foreach ($reader->readChunks($abortedStream) as $chunk) {
            break;
        }

        $factory = new Psr17Factory();
        $lines = iterator_to_array(
            $reader->readLines(
                $factory->createStream(self::UTF8_BOM . "{\"c\":3}\n{\"d\":4}"),
                "/\r?\n/",
            ),
            false,
        );

        self::assertSame(['{"c":3}', '{"d":4}'], $lines);
    }

    #[Test]
    public function reparsing_consumed_stream_returns_empty_without_duplicates(): void
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream('{"id":1}' . "\n" . '{"id":2}');

        $first = $this->parser->parseStream($stream, 'application/jsonl');
        $second = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertCount(2, $first);
        self::assertSame([], $second);
    }

    #[Test]
    public function parse_stream_accepts_stream_with_unknown_size(): void
    {
        $stream = $this->createChunkedStream(
            '{"id":1}' . "\n" . '{"id":2}',
            self::STUB_STREAM_CHUNK_LIMIT,
            declaredSize: null,
        );

        $result = $this->parser->parseStream($stream, 'application/jsonl');

        self::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    private function createChunkedStream(
        string $payload,
        int $chunkSizeLimit,
        ?int $declaredSize = null,
    ): StreamInterface {
        return new class ($payload, $chunkSizeLimit, $declaredSize) implements StreamInterface {
            private int $position = 0;

            public function __construct(
                private readonly string $payload,
                private readonly int $chunkSizeLimit,
                private readonly ?int $declaredSize,
            ) {}

            public function __toString(): string
            {
                return $this->payload;
            }

            public function close(): void {}

            public function detach(): ?int
            {
                return null;
            }

            public function getSize(): ?int
            {
                return $this->declaredSize;
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                return $this->position >= strlen($this->payload);
            }

            public function isSeekable(): bool
            {
                return false;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                throw new RuntimeException('Chunked test stream is not seekable');
            }

            public function rewind(): void
            {
                throw new RuntimeException('Chunked test stream is not seekable');
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new RuntimeException('Chunked test stream is not writable');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                $size = min($length, $this->chunkSizeLimit, strlen($this->payload) - $this->position);

                if (0 >= $size) {
                    return '';
                }

                $chunk = substr($this->payload, $this->position, $size);
                $this->position += $size;

                return $chunk;
            }

            public function getContents(): string
            {
                $contents = substr($this->payload, $this->position);
                $this->position = strlen($this->payload);

                return false === $contents ? '' : $contents;
            }

            public function getMetadata(?string $key = null): mixed
            {
                return null;
            }
        };
    }
}
