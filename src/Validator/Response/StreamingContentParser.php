<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Validator\Response\Internal\JsonSeqParser;
use Duyler\OpenApi\Validator\Response\Internal\NdJsonParser;
use Duyler\OpenApi\Validator\Response\Internal\RecordCollector;
use Duyler\OpenApi\Validator\Response\Internal\SseParser;
use Duyler\OpenApi\Validator\Response\Internal\StreamLineReader;
use Duyler\OpenApi\Validator\Response\Internal\StreamingFormatParser;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the three streaming response formats supported by the validator:
 * NDJSON / JSON Lines (`application/jsonl`, `application/x-ndjson`),
 * Server-Sent Events (`text/event-stream`), and JSON Text Sequences
 * (`application/json-seq`, RFC 7464).
 *
 * Acts as a dispatcher: routes by Content-Type to one of three format
 * parsers under StreamingFormatParser. Format-specific semantics (BOM
 * handling, malformed-record policy, SSE event assembly, JSON-Seq record
 * separator) live in the parsers; this class only owns the
 * `strictStreaming` constructor flag, the record-count cap, and the
 * per-format line/record size limits.
 */
final readonly class StreamingContentParser
{
    private const int DEFAULT_MAX_LINE_LENGTH = StreamLineReader::DEFAULT_MAX_LINE_LENGTH;
    private const int DEFAULT_MAX_RECORD_LENGTH = JsonSeqParser::DEFAULT_MAX_RECORD_LENGTH;
    private const int DEFAULT_MAX_RECORDS = RecordCollector::DEFAULT_MAX_RECORDS;

    private NdJsonParser $ndJson;
    private SseParser $sse;
    private JsonSeqParser $jsonSeq;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxLineLength = self::DEFAULT_MAX_LINE_LENGTH,
        private readonly int $maxRecordLength = self::DEFAULT_MAX_RECORD_LENGTH,
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = self::DEFAULT_MAX_RECORDS,
    ) {
        $lineReader = new StreamLineReader($maxLineLength);

        $this->ndJson = new NdJsonParser(
            logger: $logger,
            strictStreaming: $strictStreaming,
            maxRecords: $maxRecords,
            lineReader: $lineReader,
        );
        $this->sse = new SseParser(
            logger: $logger,
            strictStreaming: $strictStreaming,
            maxRecords: $maxRecords,
            lineReader: $lineReader,
        );
        $this->jsonSeq = new JsonSeqParser(
            logger: $logger,
            maxRecordLength: $maxRecordLength,
            strictStreaming: $strictStreaming,
            maxRecords: $maxRecords,
            lineReader: $lineReader,
        );
    }

    /**
     * Resolve the format parser responsible for the given content type,
     * using the same detection semantics as StreamingMediaTypeDetector.
     *
     * @internal
     */
    public function parserFor(string $contentType): ?StreamingFormatParser
    {
        return match (StreamingMediaTypeDetector::detect($contentType)) {
            StreamingFormat::NdJson => $this->ndJson,
            StreamingFormat::Sse => $this->sse,
            StreamingFormat::JsonSeq => $this->jsonSeq,
            null => null,
        };
    }

    /**
     * Parse streaming content based on content type.
     *
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function parse(string $body, string $contentType): array
    {
        $parser = $this->parserFor($contentType);

        return null === $parser ? [] : $parser->parseString($body);
    }

    /**
     * Parse a PSR-7 stream in chunks without loading the entire body into memory.
     *
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function parseStream(StreamInterface $stream, string $contentType): array
    {
        if (0 === $stream->getSize()) {
            return [];
        }

        $parser = $this->parserFor($contentType);

        return null === $parser ? [] : $parser->parseStream($stream);
    }

    /**
     * Parse JSON Lines (NDJSON) format.
     *
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function parseJsonLines(string $body): array
    {
        return $this->ndJson->parseString($body);
    }

    /**
     * Parse Server-Sent Events format.
     *
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function parseServerSentEvents(string $body): array
    {
        return $this->sse->parseString($body);
    }

    /**
     * Parse JSON Text Sequences (RFC 7464).
     *
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function parseJsonSeq(string $body): array
    {
        return $this->jsonSeq->parseString($body);
    }
}
