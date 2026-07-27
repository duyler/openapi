<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Generator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function assert;
use function is_array;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/** @internal */
final readonly class StreamLineReader
{
    public const string UTF8_BOM = "\xEF\xBB\xBF";
    public const int STREAM_CHUNK_SIZE = 8192;
    public const int DEFAULT_MAX_LINE_LENGTH = 1_048_576;

    public function __construct(
        private readonly int $maxLineLength = self::DEFAULT_MAX_LINE_LENGTH,
    ) {}

    /**
     * @param non-empty-string $splitPattern
     *
     * @return Generator<int, string, void, void>
     */
    public function readLines(StreamInterface $stream, string $splitPattern): Generator
    {
        $buffer = '';
        $bomStripped = false;

        while (null !== ($chunk = $this->readChunk($stream, $bomStripped))) {
            $buffer .= $chunk;
            $this->enforceLineLength($buffer);

            $lines = preg_split($splitPattern, $buffer);
            assert(is_array($lines));

            /** @var string $buffer */
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                yield $line;
            }
        }

        yield $buffer;
    }

    public static function stripBom(string $body): string
    {
        if (str_starts_with($body, self::UTF8_BOM)) {
            return substr($body, strlen(self::UTF8_BOM));
        }

        return $body;
    }

    /** @param-out bool $bomStripped */
    private function readChunk(StreamInterface $stream, bool &$bomStripped): ?string
    {
        if ($stream->eof()) {
            return null;
        }

        $chunk = $stream->read(self::STREAM_CHUNK_SIZE);

        if ('' === $chunk) {
            return null;
        }

        if (false === $bomStripped) {
            $chunk = self::stripBom($chunk);
            $bomStripped = true;
        }

        return $chunk;
    }

    private function enforceLineLength(string $buffer): void
    {
        if (strlen($buffer) > $this->maxLineLength) {
            throw new RuntimeException(sprintf(
                'Stream line exceeds maximum allowed length of %d bytes',
                $this->maxLineLength,
            ));
        }
    }
}
