<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Response\Exception\StreamRecordTooLargeException;
use Duyler\OpenApi\Validator\Response\StreamingFormat;
use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_pop;
use function explode;
use function strlen;
use function strpos;
use function substr;

/** @internal */
final readonly class JsonSeqParser implements StreamingFormatParser
{
    public const string RECORD_SEPARATOR = "\x1E";
    public const int DEFAULT_MAX_RECORD_LENGTH = 10_485_760;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxRecordLength = self::DEFAULT_MAX_RECORD_LENGTH,
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = RecordCollector::DEFAULT_MAX_RECORDS,
        private readonly StreamLineReader $lineReader = new StreamLineReader(),
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $collector = $this->createCollector();

        $body = StreamLineReader::stripBom($body);

        $pos = 0;
        $length = strlen($body);

        while ($pos < $length) {
            if (self::RECORD_SEPARATOR === substr($body, $pos, 1)) {
                ++$pos;
            }

            $endPos = strpos($body, self::RECORD_SEPARATOR, $pos);
            if (false === $endPos) {
                $endPos = $length;
            }

            $collector->appendDecoded(substr($body, $pos, $endPos - $pos));

            $pos = $endPos;
        }

        return $collector->records();
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        $collector = $this->createCollector();
        $buffer = '';

        foreach ($this->lineReader->readChunks($stream) as $chunk) {
            $buffer .= $chunk;
            $this->enforceRecordLength($buffer);
            $buffer = $this->consumeCompleteRecords($buffer, $collector);
        }

        $collector->appendDecoded($buffer);

        return $collector->records();
    }

    private function enforceRecordLength(string $buffer): void
    {
        if (strlen($buffer) > $this->maxRecordLength) {
            throw StreamRecordTooLargeException::forJsonSequenceRecord($this->maxRecordLength);
        }
    }

    private function consumeCompleteRecords(string $buffer, RecordCollector $collector): string
    {
        $records = explode(self::RECORD_SEPARATOR, $buffer);

        /** @var string $buffer */
        $buffer = array_pop($records);

        foreach ($records as $record) {
            $collector->appendDecoded($record);
        }

        return $buffer;
    }

    private function createCollector(): RecordCollector
    {
        return new RecordCollector(
            format: StreamingFormat::JsonSeq,
            logger: $this->logger,
            strictStreaming: $this->strictStreaming,
            maxRecords: $this->maxRecords,
        );
    }
}
