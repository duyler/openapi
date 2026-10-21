<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Response\StreamingFormat;
use JsonException;
use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function assert;
use function ctype_digit;
use function is_array;
use function json_decode;
use function preg_split;
use function str_starts_with;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class SseParser implements StreamingFormatParser
{
    private const string SSE_LINE_SPLIT_PATTERN = '/\r\n|\r|\n/';
    private const string SSE_DEFAULT_EVENT_TYPE = 'message';
    private const string SSE_FIELD_SPACE = ' ';

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = RecordCollector::DEFAULT_MAX_RECORDS,
        private readonly StreamLineReader $lineReader = new StreamLineReader(),
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $lines = preg_split(self::SSE_LINE_SPLIT_PATTERN, StreamLineReader::stripBom($body));
        assert(is_array($lines));

        return $this->parseLines($lines, $this->createCollector());
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        return $this->parseLines(
            $this->lineReader->readLines($stream, self::SSE_LINE_SPLIT_PATTERN),
            $this->createCollector(),
        );
    }

    /**
     * @param iterable<string> $lines
     * @return list<array<int|string, mixed>|scalar|null>
     */
    private function parseLines(iterable $lines, RecordCollector $collector): array
    {
        /** @var array<string, string> $currentEvent */
        $currentEvent = [];

        foreach ($lines as $line) {
            [$currentEvent, $completedEvent] = $this->processLine($line, $currentEvent, $collector);

            if (null !== $completedEvent) {
                $collector->append($completedEvent);
            }
        }

        if ([] !== $currentEvent) {
            $collector->append($this->formatEvent($currentEvent, $collector));
        }

        return $collector->records();
    }

    private function createCollector(): RecordCollector
    {
        return new RecordCollector(
            format: StreamingFormat::Sse,
            logger: $this->logger,
            strictStreaming: $this->strictStreaming,
            maxRecords: $this->maxRecords,
        );
    }

    /**
     * @param array<string, string> $currentEvent
     * @return array{0: array<string, string>, 1: array<int|string, mixed>|null}
     */
    private function processLine(string $line, array $currentEvent, RecordCollector $collector): array
    {
        if ('' === $line) {
            if ([] !== $currentEvent) {
                return [[], $this->formatEvent($currentEvent, $collector)];
            }

            return [$currentEvent, null];
        }

        if (str_starts_with($line, ':')) {
            return [$currentEvent, null];
        }

        $colonPos = strpos($line, ':');

        if (false !== $colonPos) {
            $field = substr($line, 0, $colonPos);
            $rawValue = substr($line, $colonPos + 1);
            $value = str_starts_with($rawValue, self::SSE_FIELD_SPACE)
                ? substr($rawValue, 1)
                : $rawValue;

            return [$this->applyField($currentEvent, $field, $value), null];
        }

        return [$this->applyField($currentEvent, $line, ''), null];
    }

    /**
     * @param array<string, string> $currentEvent
     * @return array<string, string>
     */
    private function applyField(array $currentEvent, string $field, string $value): array
    {
        if ('data' === $field && isset($currentEvent['data']) && '' !== $currentEvent['data']) {
            $currentEvent['data'] .= "\n" . $value;

            return $currentEvent;
        }

        $currentEvent[$field] = $value;

        return $currentEvent;
    }

    /**
     * @param array<string, string> $event
     * @return array<int|string, mixed>
     */
    private function formatEvent(array $event, RecordCollector $collector): array
    {
        $result = [];

        if (isset($event['event'])) {
            $result['event'] = $event['event'];
        } elseif (isset($event['data'])) {
            $result['event'] = self::SSE_DEFAULT_EVENT_TYPE;
        }

        if (isset($event['data'])) {
            $result['data'] = $this->decodeData($event['data'], $collector);
        }

        if (isset($event['id'])) {
            $result['id'] = $event['id'];
        }

        if (isset($event['retry'])) {
            if (ctype_digit($event['retry'])) {
                $result['retry'] = (int) $event['retry'];
            }
        }

        return $result;
    }

    /**
     * @return array<int|string, mixed>|scalar|null
     */
    private function decodeData(string $data, RecordCollector $collector): mixed
    {
        try {
            /** @var array<int|string, mixed>|scalar|null $decoded */
            $decoded = json_decode($data, true, RecordCollector::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException $exception) {
            $collector->reportMalformed($data, $exception);

            return $data;
        }
    }
}
