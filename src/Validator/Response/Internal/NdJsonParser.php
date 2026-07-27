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
use RuntimeException;

use function assert;
use function count;
use function is_array;
use function json_decode;
use function sprintf;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class NdJsonParser implements StreamingFormatParser
{
    private const string NDJSON_LINE_SPLIT_PATTERN = '/\r?\n/';
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;
    private const int DEFAULT_MAX_RECORDS = 100_000;
    private const int LOG_RECORD_TRUNCATE_LENGTH = 256;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = self::DEFAULT_MAX_RECORDS,
        private readonly int $maxLineLength = StreamLineReader::DEFAULT_MAX_LINE_LENGTH,
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $body = StreamLineReader::stripBom($body);

        $lines = preg_split(self::NDJSON_LINE_SPLIT_PATTERN, trim($body));
        assert(is_array($lines));

        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $recordCount = 0;

        foreach ($lines as $line) {
            $before = count($items);
            $items = $this->appendItem($items, $line);
            if (count($items) === $before) {
                continue;
            }
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        return $items;
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $recordCount = 0;
        $buffer = '';
        $bomStripped = false;

        while (!$stream->eof()) {
            $chunk = $stream->read(StreamLineReader::STREAM_CHUNK_SIZE);

            if ('' === $chunk) {
                break;
            }

            if (false === $bomStripped) {
                $chunk = StreamLineReader::stripBom($chunk);
                $bomStripped = true;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > $this->maxLineLength) {
                throw new RuntimeException(sprintf(
                    'Stream line exceeds maximum allowed length of %d bytes',
                    $this->maxLineLength,
                ));
            }

            $lines = preg_split(self::NDJSON_LINE_SPLIT_PATTERN, $buffer);
            assert(is_array($lines));

            /** @var string $buffer */
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $before = count($items);
                $items = $this->appendItem($items, $line);
                if (count($items) === $before) {
                    continue;
                }
                ++$recordCount;
                $this->enforceRecordLimit($recordCount);
            }
        }

        $before = count($items);
        $items = $this->appendItem($items, $buffer);
        if (count($items) !== $before) {
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        return $items;
    }

    private function enforceRecordLimit(int $recordCount): void
    {
        if ($recordCount > $this->maxRecords) {
            throw new TooManyRecordsException(max: $this->maxRecords);
        }
    }

    /**
     * @param list<array<int|string, mixed>|null> $items
     *
     * @return list<array<int|string, mixed>|null>
     */
    private function appendItem(array $items, string $line): array
    {
        if ('' === trim($line)) {
            return $items;
        }

        try {
            /** @var array<int|string, mixed> $decoded */
            $decoded = json_decode($line, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            $items[] = $decoded;
        } catch (JsonException $exception) {
            if ($this->strictStreaming) {
                throw new MalformedStreamRecordException($line, $exception);
            }
            $this->logger->warning('Failed to parse JSON line in NDJSON stream', [
                'line' => LogContextSanitizer::truncate($line, self::LOG_RECORD_TRUNCATE_LENGTH),
                'exception' => $exception,
            ]);
            $items[] = null;
        }

        return $items;
    }
}
