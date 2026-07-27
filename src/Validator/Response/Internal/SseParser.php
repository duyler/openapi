<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use JsonException;
use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function assert;
use function count;
use function ctype_digit;
use function is_array;
use function json_decode;
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
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;
    private const int DEFAULT_MAX_RECORDS = 100_000;
    private const int LOG_RECORD_TRUNCATE_LENGTH = 256;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = self::DEFAULT_MAX_RECORDS,
        private readonly StreamLineReader $lineReader = new StreamLineReader(),
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $body = StreamLineReader::stripBom($body);

        /** @var list<array<int|string, mixed>|null> $events */
        $events = [];
        /** @var array<string, string> $currentEvent */
        $currentEvent = [];
        $recordCount = 0;

        $lines = preg_split(self::SSE_LINE_SPLIT_PATTERN, $body);
        assert(is_array($lines));

        foreach ($lines as $line) {
            $before = count($events);
            [$currentEvent, $events] = $this->processLine($line, $currentEvent, $events);
            if (count($events) === $before) {
                continue;
            }
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        if ([] !== $currentEvent) {
            $events[] = $this->formatEvent($currentEvent);
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        return $events;
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $events */
        $events = [];
        /** @var array<string, string> $currentEvent */
        $currentEvent = [];
        $recordCount = 0;

        foreach ($this->lineReader->readLines($stream, self::SSE_LINE_SPLIT_PATTERN) as $line) {
            $before = count($events);
            [$currentEvent, $events] = $this->processLine($line, $currentEvent, $events);
            if (count($events) === $before) {
                continue;
            }
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        if ([] !== $currentEvent) {
            $events[] = $this->formatEvent($currentEvent);
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        return $events;
    }

    private function enforceRecordLimit(int $recordCount): void
    {
        if ($recordCount > $this->maxRecords) {
            throw new TooManyRecordsException(max: $this->maxRecords);
        }
    }

    /**
     * @param array<string, string>              $currentEvent
     * @param list<array<int|string, mixed>|null> $events
     *
     * @return array{0: array<string, string>, 1: list<array<int|string, mixed>|null>}
     */
    private function processLine(string $line, array $currentEvent, array $events): array
    {
        if ('' === $line) {
            if ([] !== $currentEvent) {
                $events[] = $this->formatEvent($currentEvent);

                return [[], $events];
            }

            return [$currentEvent, $events];
        }

        if (str_starts_with($line, ':')) {
            return [$currentEvent, $events];
        }

        $colonPos = strpos($line, ':');

        if (false !== $colonPos) {
            $field = substr($line, 0, $colonPos);
            $rawValue = substr($line, $colonPos + 1);
            $value = str_starts_with($rawValue, self::SSE_FIELD_SPACE)
                ? substr($rawValue, 1)
                : $rawValue;

            return [$this->applyField($currentEvent, $field, $value), $events];
        }

        return [$this->applyField($currentEvent, $line, ''), $events];
    }

    /**
     * @param array<string, string> $currentEvent
     *
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
     *
     * @return array<int|string, mixed>
     */
    private function formatEvent(array $event): array
    {
        $result = [];

        if (isset($event['event'])) {
            $result['event'] = $event['event'];
        } elseif (isset($event['data'])) {
            $result['event'] = self::SSE_DEFAULT_EVENT_TYPE;
        }

        if (isset($event['data'])) {
            $result['data'] = $this->decodeData($event['data']);
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

    /**
     * @return array<int|string, mixed>|scalar|null
     */
    private function decodeData(string $data): mixed
    {
        try {
            /** @var array<int|string, mixed>|scalar|null $decoded */
            $decoded = json_decode($data, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException $exception) {
            if ($this->strictStreaming) {
                throw new MalformedStreamRecordException($data, $exception);
            }
            $this->logger->warning('Failed to parse SSE event data as JSON, using raw value', [
                'data' => LogContextSanitizer::truncate($data, self::LOG_RECORD_TRUNCATE_LENGTH),
                'exception' => $exception,
            ]);

            return $data;
        }
    }
}
