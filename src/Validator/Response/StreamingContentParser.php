<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use JsonException;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function assert;
use function ctype_digit;
use function is_array;
use function is_null;
use function is_scalar;
use function sprintf;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class StreamingContentParser
{
    private const string RECORD_SEPARATOR = "\x1E";
    private const int JSON_MAX_DEPTH = 512;
    private const int STREAM_CHUNK_SIZE = 8192;
    private const string UTF8_BOM = "\xEF\xBB\xBF";
    private const int DEFAULT_MAX_LINE_LENGTH = 1_048_576;
    private const int DEFAULT_MAX_RECORD_LENGTH = 10_485_760;

    /**
     * W3C Server-Sent Events default event type used when the event field is absent.
     *
     * @see https://html.spec.whatwg.org/multipage/server-sent-events.html
     */
    private const string SSE_DEFAULT_EVENT_TYPE = 'message';

    /**
     * Line-splitting pattern covering all three line-ending variants allowed by
     * WHATWG HTML SSE §8.1.1: CRLF, LF, and CR.
     *
     * @see https://html.spec.whatwg.org/multipage/server-sent-events.html#parsing-an-event-stream
     */
    private const string SSE_LINE_SPLIT_PATTERN = '/\r\n|\r|\n/';

    /**
     * Single U+0020 SPACE removed from the start of an SSE field value per
     * WHATWG HTML SSE §8.2.6 (exactly one, never tabs or other whitespace).
     */
    private const string SSE_FIELD_SPACE = ' ';

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxLineLength = self::DEFAULT_MAX_LINE_LENGTH,
        private readonly int $maxRecordLength = self::DEFAULT_MAX_RECORD_LENGTH,
    ) {}

    /**
     * Parse streaming content based on content type
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parse(string $body, string $contentType): array
    {
        return match (true) {
            str_contains($contentType, 'application/jsonl'),
            str_contains($contentType, 'application/x-ndjson') => $this->parseJsonLines($body),
            str_contains($contentType, 'text/event-stream') => $this->parseServerSentEvents($body),
            str_contains($contentType, 'application/json-seq') => $this->parseJsonSeq($body),
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
            str_contains($contentType, 'application/x-ndjson') => $this->parseJsonLinesFromStream($stream),
            str_contains($contentType, 'text/event-stream') => $this->parseServerSentEventsFromStream($stream),
            str_contains($contentType, 'application/json-seq') => $this->parseJsonSeqFromStream($stream),
            default => [],
        };
    }

    /**
     * Parse JSON Lines (NDJSON) format
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonLines(string $body): array
    {
        $body = $this->stripBom($body);

        $lines = preg_split('/\r?\n/', trim($body));
        assert(is_array($lines));
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];

        foreach ($lines as $line) {
            $this->appendJsonLine($items, $line);
        }

        return $items;
    }

    /**
     * Parse Server-Sent Events format
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseServerSentEvents(string $body): array
    {
        $body = $this->stripBom($body);

        /** @var list<array<int|string, mixed>|null> $events */
        $events = [];
        $currentEvent = [];

        $lines = preg_split(self::SSE_LINE_SPLIT_PATTERN, $body);
        if (false === $lines) {
            return [];
        }

        foreach ($lines as $line) {
            if ('' === $line) {
                if ([] !== $currentEvent) {
                    $events[] = $this->formatSseEvent($currentEvent);
                    $currentEvent = [];
                }
                continue;
            }

            if (str_starts_with($line, ':')) {
                continue;
            }

            $colonPos = strpos($line, ':');
            if (false !== $colonPos) {
                $field = substr($line, 0, $colonPos);
                $rawValue = substr($line, $colonPos + 1);
                $value = str_starts_with($rawValue, self::SSE_FIELD_SPACE)
                    ? substr($rawValue, 1)
                    : $rawValue;
                $this->applySseField($currentEvent, $field, $value);
            }
        }

        if ([] !== $currentEvent) {
            $events[] = $this->formatSseEvent($currentEvent);
        }

        return $events;
    }

    /**
     * Parse JSON Text Sequences (RFC 7464)
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonSeq(string $body): array
    {
        $body = $this->stripBom($body);

        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $pos = 0;
        $length = strlen($body);

        while ($pos < $length) {
            if (self::RECORD_SEPARATOR === substr($body, $pos, 1)) {
                $pos++;
            }

            $endPos = strpos($body, self::RECORD_SEPARATOR, $pos);
            if (false === $endPos) {
                $endPos = $length;
            }

            $json = substr($body, $pos, $endPos - $pos);
            $json = trim($json);

            if ('' !== $json) {
                $this->appendJsonSeqItem($items, $json);
            }

            $pos = $endPos;
        }

        return $items;
    }

    private function stripBom(string $body): string
    {
        if (str_starts_with($body, self::UTF8_BOM)) {
            return substr($body, strlen(self::UTF8_BOM));
        }

        return $body;
    }

    /**
     * @param list<array<int|string, mixed>|null> $items
     */
    private function appendJsonLine(array &$items, string $line): void
    {
        if ('' === trim($line)) {
            return;
        }

        try {
            /** @var array<int|string, mixed> $decoded */
            $decoded = json_decode($line, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            $items[] = $decoded;
        } catch (JsonException $exception) {
            $this->logger->warning('Failed to parse JSON line in NDJSON stream', [
                'line' => $line,
                'exception' => $exception,
            ]);
            $items[] = null;
        }
    }

    /**
     * @param list<array<int|string, mixed>|null> $items
     */
    private function appendJsonSeqItem(array &$items, string $json): void
    {
        $json = trim($json);

        if ('' === $json) {
            return;
        }

        try {
            /** @var array<int|string, mixed> $decoded */
            $decoded = json_decode($json, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            $items[] = $decoded;
        } catch (JsonException $exception) {
            $this->logger->warning('Failed to parse JSON sequence item', [
                'json' => $json,
                'exception' => $exception,
            ]);
            $items[] = null;
        }
    }

    /**
     * @return list<array<int|string, mixed>|null>
     */
    private function parseJsonLinesFromStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(self::STREAM_CHUNK_SIZE);

            if ('' === $chunk) {
                break;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > $this->maxLineLength) {
                throw new RuntimeException(sprintf(
                    'Stream line exceeds maximum allowed length of %d bytes',
                    $this->maxLineLength,
                ));
            }

            $lines = preg_split('/\r?\n/', $buffer);
            assert(is_array($lines));

            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $this->appendJsonLine($items, $line);
            }
        }

        $this->appendJsonLine($items, $buffer);

        return $items;
    }

    /**
     * @return list<array<int|string, mixed>|null>
     */
    private function parseServerSentEventsFromStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $events */
        $events = [];
        $buffer = '';
        $currentEvent = [];

        while (!$stream->eof()) {
            $chunk = $stream->read(self::STREAM_CHUNK_SIZE);

            if ('' === $chunk) {
                break;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > $this->maxLineLength) {
                throw new RuntimeException(sprintf(
                    'Stream line exceeds maximum allowed length of %d bytes',
                    $this->maxLineLength,
                ));
            }

            $lines = preg_split(self::SSE_LINE_SPLIT_PATTERN, $buffer);
            assert(is_array($lines));

            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $this->processSseLine($line, $currentEvent, $events);
            }
        }

        $remainingLines = preg_split(self::SSE_LINE_SPLIT_PATTERN, $buffer);
        assert(is_array($remainingLines));

        foreach ($remainingLines as $line) {
            $this->processSseLine($line, $currentEvent, $events);
        }

        if ([] !== $currentEvent) {
            $events[] = $this->formatSseEvent($currentEvent);
        }

        return $events;
    }

    /**
     * @param array<string, string> $currentEvent
     * @param list<array<int|string, mixed>|null> $events
     */
    private function processSseLine(string $line, array &$currentEvent, array &$events): void
    {
        if ('' === $line) {
            if ([] !== $currentEvent) {
                $events[] = $this->formatSseEvent($currentEvent);
                $currentEvent = [];
            }

            return;
        }

        if (str_starts_with($line, ':')) {
            return;
        }

        $colonPos = strpos($line, ':');

        if (false !== $colonPos) {
            $field = substr($line, 0, $colonPos);
            $rawValue = substr($line, $colonPos + 1);
            $value = str_starts_with($rawValue, self::SSE_FIELD_SPACE)
                ? substr($rawValue, 1)
                : $rawValue;
            $this->applySseField($currentEvent, $field, $value);
        }
    }

    /**
     * @return list<array<int|string, mixed>|null>
     */
    private function parseJsonSeqFromStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(self::STREAM_CHUNK_SIZE);

            if ('' === $chunk) {
                break;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > $this->maxRecordLength) {
                throw new RuntimeException(sprintf(
                    'JSON sequence record exceeds maximum allowed length of %d bytes',
                    $this->maxRecordLength,
                ));
            }

            $records = explode(self::RECORD_SEPARATOR, $buffer);

            $buffer = array_pop($records);

            foreach ($records as $record) {
                $this->appendJsonSeqItem($items, $record);
            }
        }

        $this->appendJsonSeqItem($items, $buffer);

        return $items;
    }

    /**
     * @param array<string, string> $currentEvent
     */
    private function applySseField(array &$currentEvent, string $field, string $value): void
    {
        if ('data' === $field && isset($currentEvent['data']) && '' !== $currentEvent['data']) {
            $currentEvent['data'] .= "\n" . $value;

            return;
        }

        $currentEvent[$field] = $value;
    }

    /**
     * Format SSE event data
     *
     * @param array<string, string> $event
     * @return array<int|string, mixed>
     */
    private function formatSseEvent(array $event): array
    {
        $result = [];

        if (isset($event['event'])) {
            $result['event'] = $event['event'];
        } elseif (isset($event['data'])) {
            $result['event'] = self::SSE_DEFAULT_EVENT_TYPE;
        }
        if (isset($event['data'])) {
            $dataValue = $event['data'];
            try {
                $decoded = json_decode($event['data'], true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
                assert(is_array($decoded) || is_null($decoded) || is_scalar($decoded));
                $dataValue = $decoded;
            } catch (JsonException $exception) {
                $this->logger->warning('Failed to parse SSE event data as JSON, using raw value', [
                    'data' => $event['data'],
                    'exception' => $exception,
                ]);
            }
            $result['data'] = $dataValue;
        }
        if (isset($event['id'])) {
            $result['id'] = $event['id'];
        }
        if (isset($event['retry'])) {
            /** @var mixed $retry */
            $retry = $event['retry'];
            if (ctype_digit((string) $retry)) {
                $result['retry'] = (int) $retry;
            }
        }

        return $result;
    }
}
