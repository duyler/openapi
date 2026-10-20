<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Response\StreamingFormat;
use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function assert;
use function is_array;
use function preg_split;
use function trim;

/** @internal */
final readonly class NdJsonParser implements StreamingFormatParser
{
    private const string NDJSON_LINE_SPLIT_PATTERN = '/\r?\n/';

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $strictStreaming = false,
        private readonly int $maxRecords = RecordCollector::DEFAULT_MAX_RECORDS,
        private readonly StreamLineReader $lineReader = new StreamLineReader(),
    ) {}

    #[Override]
    public function parseString(string $body): array
    {
        $collector = $this->createCollector();

        $lines = preg_split(self::NDJSON_LINE_SPLIT_PATTERN, trim(StreamLineReader::stripBom($body)));
        assert(is_array($lines));

        foreach ($lines as $line) {
            $collector->appendDecoded($line);
        }

        return $collector->records();
    }

    #[Override]
    public function parseStream(StreamInterface $stream): array
    {
        $collector = $this->createCollector();

        foreach ($this->lineReader->readLines($stream, self::NDJSON_LINE_SPLIT_PATTERN) as $line) {
            $collector->appendDecoded($line);
        }

        return $collector->records();
    }

    private function createCollector(): RecordCollector
    {
        return new RecordCollector(
            format: StreamingFormat::NdJson,
            logger: $this->logger,
            strictStreaming: $this->strictStreaming,
            maxRecords: $this->maxRecords,
        );
    }
}
