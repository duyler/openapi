<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Response\StreamingFormat;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;
use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class RecordCollector
{
    public const int DEFAULT_MAX_RECORDS = 100_000;
    public const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;
    private const int LOG_RECORD_TRUNCATE_LENGTH = 256;

    private const string NDJSON_MALFORMED_LOG_MESSAGE = 'Failed to parse JSON line in NDJSON stream';
    private const string NDJSON_MALFORMED_LOG_CONTEXT_KEY = 'line';
    private const string JSON_SEQ_MALFORMED_LOG_MESSAGE = 'Failed to parse JSON sequence item';
    private const string JSON_SEQ_MALFORMED_LOG_CONTEXT_KEY = 'json';
    private const string SSE_MALFORMED_LOG_MESSAGE = 'Failed to parse SSE event data as JSON, using raw value';
    private const string SSE_MALFORMED_LOG_CONTEXT_KEY = 'data';

    /** @var list<mixed> */
    private array $records = [];

    private readonly string $malformedLogMessage;
    private readonly string $malformedLogContextKey;

    public function __construct(
        private readonly StreamingFormat $format,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = self::DEFAULT_MAX_RECORDS,
    ) {
        [$this->malformedLogMessage, $this->malformedLogContextKey] = match ($this->format) {
            StreamingFormat::NdJson => [self::NDJSON_MALFORMED_LOG_MESSAGE, self::NDJSON_MALFORMED_LOG_CONTEXT_KEY],
            StreamingFormat::JsonSeq => [self::JSON_SEQ_MALFORMED_LOG_MESSAGE, self::JSON_SEQ_MALFORMED_LOG_CONTEXT_KEY],
            StreamingFormat::Sse => [self::SSE_MALFORMED_LOG_MESSAGE, self::SSE_MALFORMED_LOG_CONTEXT_KEY],
        };
    }

    public function append(mixed $record): void
    {
        $this->records[] = $record;
        $this->enforceRecordLimit();
    }

    /**
     * @return list<array<int|string, mixed>|scalar|null>
     */
    public function records(): array
    {
        /** @var list<array<int|string, mixed>|scalar|null> */
        return $this->records;
    }

    public function appendDecoded(string $payload): void
    {
        $trimmed = trim($payload);

        if ('' === $trimmed) {
            return;
        }

        try {
            /** @var array<int|string, mixed>|scalar|null $decoded */
            $decoded = json_decode($trimmed, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->reportMalformed($payload, $exception);
            $this->append(null);

            return;
        }

        $this->append($decoded);
    }

    public function reportMalformed(string $payload, JsonException $exception): void
    {
        if ($this->strictStreaming) {
            throw new MalformedStreamRecordException($payload, $exception);
        }

        $this->logger->warning($this->malformedLogMessage, [
            $this->malformedLogContextKey => LogContextSanitizer::truncate($payload, self::LOG_RECORD_TRUNCATE_LENGTH),
            'exception' => $exception,
        ]);
    }

    private function enforceRecordLimit(): void
    {
        if (count($this->records) > $this->maxRecords) {
            throw new TooManyRecordsException(max: $this->maxRecords);
        }
    }
}
