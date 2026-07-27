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

use function count;
use function explode;
use function json_decode;
use function sprintf;
use function strlen;
use function strpos;
use function trim;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class JsonSeqParser implements StreamingFormatParser
{
    public const string RECORD_SEPARATOR = "\x1E";

    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;
    private const int STREAM_CHUNK_SIZE = 8192;
    private const int DEFAULT_MAX_RECORDS = 100_000;
    private const int DEFAULT_MAX_RECORD_LENGTH = 10_485_760;
    private const int LOG_RECORD_TRUNCATE_LENGTH = 256;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxRecordLength = self::DEFAULT_MAX_RECORD_LENGTH,
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = self::DEFAULT_MAX_RECORDS,
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $body = StreamLineReader::stripBom($body);

        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $pos = 0;
        $length = strlen($body);
        $recordCount = 0;

        while ($pos < $length) {
            if (self::RECORD_SEPARATOR === substr($body, $pos, 1)) {
                ++$pos;
            }

            $endPos = strpos($body, self::RECORD_SEPARATOR, $pos);
            if (false === $endPos) {
                $endPos = $length;
            }

            $before = count($items);
            $items = $this->appendItem($items, substr($body, $pos, $endPos - $pos));
            if (count($items) > $before) {
                ++$recordCount;
                $this->enforceRecordLimit($recordCount);
            }

            $pos = $endPos;
        }

        return $items;
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $buffer = '';
        $isBomStripped = false;
        $recordCount = 0;

        while (null !== ($chunk = $this->readChunk($stream, $isBomStripped))) {
            $buffer .= $chunk;
            $this->enforceRecordLength($buffer);
            [$buffer, $items, $recordCount] = $this->consumeCompleteRecords($buffer, $items, $recordCount);
        }

        [$items, $recordCount] = $this->finalizeBuffer($buffer, $items, $recordCount);

        return $items;
    }

    /** @param-out bool $isBomStripped */
    private function readChunk(StreamInterface $stream, bool &$isBomStripped): ?string
    {
        if ($stream->eof()) {
            return null;
        }

        $chunk = $stream->read(self::STREAM_CHUNK_SIZE);

        if ('' === $chunk) {
            return null;
        }

        if (false === $isBomStripped) {
            $chunk = StreamLineReader::stripBom($chunk);
            $isBomStripped = true;
        }

        return $chunk;
    }

    private function enforceRecordLength(string $buffer): void
    {
        if (strlen($buffer) > $this->maxRecordLength) {
            throw new RuntimeException(sprintf(
                'JSON sequence record exceeds maximum allowed length of %d bytes',
                $this->maxRecordLength,
            ));
        }
    }

    /**
     * @param list<array<int|string, mixed>|null> $items
     *
     * @return array{0: string, 1: list<array<int|string, mixed>|null>, 2: int}
     */
    private function consumeCompleteRecords(string $buffer, array $items, int $recordCount): array
    {
        $records = explode(self::RECORD_SEPARATOR, $buffer);
        /** @var string $buffer */
        $buffer = array_pop($records);

        foreach ($records as $record) {
            $before = count($items);
            $items = $this->appendItem($items, $record);
            if (count($items) > $before) {
                ++$recordCount;
                $this->enforceRecordLimit($recordCount);
            }
        }

        return [$buffer, $items, $recordCount];
    }

    /**
     * @param list<array<int|string, mixed>|null> $items
     *
     * @return array{0: list<array<int|string, mixed>|null>, 1: int}
     */
    private function finalizeBuffer(string $buffer, array $items, int $recordCount): array
    {
        $before = count($items);
        $items = $this->appendItem($items, $buffer);
        if (count($items) > $before) {
            ++$recordCount;
            $this->enforceRecordLimit($recordCount);
        }

        return [$items, $recordCount];
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
    private function appendItem(array $items, string $json): array
    {
        $json = trim($json);

        if ('' === $json) {
            return $items;
        }

        try {
            /** @var array<int|string, mixed> $decoded */
            $decoded = json_decode($json, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            $items[] = $decoded;
        } catch (JsonException $exception) {
            if ($this->strictStreaming) {
                throw new MalformedStreamRecordException($json, $exception);
            }
            $this->logger->warning('Failed to parse JSON sequence item', [
                'json' => LogContextSanitizer::truncate($json, self::LOG_RECORD_TRUNCATE_LENGTH),
                'exception' => $exception,
            ]);
            $items[] = null;
        }

        return $items;
    }
}
