<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Validator\Response\Internal\JsonSeqParser;
use Duyler\OpenApi\Validator\Response\Internal\NdJsonParser;
use Duyler\OpenApi\Validator\Response\Internal\SseParser;
use Duyler\OpenApi\Validator\Response\Internal\StreamLineReader;
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
 * `enableStrictStreaming()` toggle, the record-count cap, and the
 * per-format line/record size limits.
 */
final readonly class StreamingContentParser
{
    private const int DEFAULT_MAX_LINE_LENGTH = 1_048_576;
    private const int DEFAULT_MAX_RECORD_LENGTH = 10_485_760;
    private const int DEFAULT_MAX_RECORDS = 100_000;

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
        $this->ndJson = new NdJsonParser($logger, $strictStreaming, $maxRecords, $lineReader);
        $this->sse = new SseParser($logger, $strictStreaming, $maxRecords, $lineReader);
        $this->jsonSeq = new JsonSeqParser($logger, $maxRecordLength, $strictStreaming, $maxRecords);
    }

    /**
     * Parse streaming content based on content type.
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parse(string $body, string $contentType): array
    {
        return match (true) {
            str_contains($contentType, 'application/jsonl'),
            str_contains($contentType, 'application/x-ndjson') => $this->ndJson->parseString($body),
            str_contains($contentType, 'text/event-stream') => $this->sse->parseString($body),
            str_contains($contentType, 'application/json-seq') => $this->jsonSeq->parseString($body),
            default => [],
        };
    }

    /**
     * Parse a PSR-7 stream in chunks without loading the entire body into memory.
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseStream(StreamInterface $stream, string $contentType): array
    {
        return match (true) {
            str_contains($contentType, 'application/jsonl'),
            str_contains($contentType, 'application/x-ndjson') => $this->ndJson->parseStream($stream),
            str_contains($contentType, 'text/event-stream') => $this->sse->parseStream($stream),
            str_contains($contentType, 'application/json-seq') => $this->jsonSeq->parseStream($stream),
            default => [],
        };
    }

    /**
     * Parse JSON Lines (NDJSON) format.
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonLines(string $body): array
    {
        return $this->ndJson->parseString($body);
    }

    /**
     * Parse Server-Sent Events format.
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseServerSentEvents(string $body): array
    {
        return $this->sse->parseString($body);
    }

    /**
     * Parse JSON Text Sequences (RFC 7464).
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonSeq(string $body): array
    {
        return $this->jsonSeq->parseString($body);
    }
}
