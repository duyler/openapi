<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Exception\MultipartBodyTooLargeException;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Response\Internal\StreamLineReader;
use Generator;
use Psr\Http\Message\StreamInterface;

use function count;
use function max;
use function str_contains;
use function strlen;
use function strpos;
use function substr;
use function trim;

/** @internal */
final readonly class MultipartStreamSplitter
{
    private const string CRLF = "\r\n";

    private const string CLOSE_MARKER = '--';

    private const string LWSP_CHARACTERS = " \t";

    private const int TAIL_CLOSE = 0;

    private const int TAIL_DELIMITER_LINE = 1;

    private const int TAIL_INCOMPLETE = 2;

    private const int TAIL_NOT_DELIMITER = 3;

    public function __construct(
        private readonly string $fullContentType,
        private readonly int $maxParts = MultipartBodyParser::MAX_MULTIPART_PARTS,
        private readonly int $maxPartBytes = ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES,
    ) {}

    /**
     * @return Generator<int, array{headers: string, content: string}, void, int>
     */
    public function parts(StreamInterface $stream): Generator
    {
        $boundary = MultipartBodyParser::boundaryFromContentType($this->fullContentType);
        $buffer = '';
        $scanned = 0;
        $inParts = false;
        $parts = 0;
        $bytes = 0;

        foreach (new StreamLineReader()->readChunks($stream) as $chunk) {
            $buffer .= $chunk;
            $bytes += strlen($chunk);
            $this->assertWithinPartBudget(strlen($buffer));

            if (null === $boundary && false === $inParts && str_contains($buffer, "\n")) {
                $boundary = MultipartBodyParser::boundaryFromBody($buffer);
            }

            if (null === $boundary) {
                continue;
            }

            $delimiter = self::CRLF . self::CLOSE_MARKER . $boundary;
            $firstDelimiter = self::CLOSE_MARKER . $boundary;

            while (true) {
                if (false === $inParts) {
                    $state = $this->preambleState($buffer, $firstDelimiter);

                    if (null === $state[0] || self::TAIL_INCOMPLETE === $state[1]) {
                        break;
                    }

                    $inParts = true;
                    $buffer = substr($buffer, $state[0] + strlen($firstDelimiter));
                    $scanned = 0;

                    if (self::TAIL_CLOSE === $state[1]) {
                        return $bytes;
                    }

                    continue;
                }

                $position = strpos($buffer, $delimiter, $scanned);

                if (false === $position) {
                    $scanned = max(0, strlen($buffer) - strlen($delimiter) + 1);

                    break;
                }

                $tail = $this->delimiterTail($buffer, $position + strlen($delimiter));

                if (self::TAIL_NOT_DELIMITER === $tail) {
                    $scanned = $position + 1;

                    continue;
                }

                if (self::TAIL_INCOMPLETE === $tail) {
                    $scanned = $position;

                    break;
                }

                $raw = substr($buffer, 0, $position);
                $closed = self::TAIL_CLOSE === $tail;
                $buffer = substr($buffer, $position + strlen($delimiter));
                $scanned = 0;
                $part = $this->part($raw);

                if (null !== $part) {
                    ++$parts;
                    $this->assertWithinPartCount($parts);

                    yield $part;
                }

                if ($closed) {
                    return $bytes;
                }
            }
        }

        if (null === $boundary) {
            $boundary = MultipartBodyParser::boundaryFromBody($buffer);
        }

        if (null === $boundary) {
            return $bytes;
        }

        $delimiter = self::CRLF . self::CLOSE_MARKER . $boundary;

        if ($inParts && '' !== $buffer) {
            $position = $this->pendingDelimiterPosition($buffer, $delimiter, $scanned);

            if (null !== $position
                && $this->endsWithLenientClose($buffer, $position + strlen($delimiter))
            ) {
                $part = $this->part(substr($buffer, 0, $position));

                if (null !== $part) {
                    ++$parts;
                    $this->assertWithinPartCount($parts);

                    yield $part;
                }

                return $bytes;
            }

            $part = $this->part($buffer);

            if (null !== $part) {
                ++$parts;
                $this->assertWithinPartCount($parts);

                yield $part;
            }
        }

        return $bytes;
    }

    /**
     * @return array{int|null, self::TAIL_*}
     */
    private function preambleState(string $buffer, string $firstDelimiter): array
    {
        $searchFrom = 0;

        while (true) {
            $offset = $this->firstDelimiterOffset($buffer, $firstDelimiter, $searchFrom);

            if (null === $offset) {
                return [null, self::TAIL_INCOMPLETE];
            }

            $tail = $this->delimiterTail($buffer, $offset + strlen($firstDelimiter));

            if (self::TAIL_NOT_DELIMITER === $tail) {
                $searchFrom = $offset + 1;

                continue;
            }

            return [$offset, $tail];
        }
    }

    private function assertWithinPartBudget(int $bufferBytes): void
    {
        if ($bufferBytes > $this->maxPartBytes) {
            throw new BodyTooLargeException($bufferBytes, $this->maxPartBytes);
        }
    }

    private function assertWithinPartCount(int $parts): void
    {
        if ($parts > $this->maxParts) {
            throw new MultipartBodyTooLargeException($this->maxParts);
        }
    }

    private function firstDelimiterOffset(string $buffer, string $firstDelimiter, int $from): ?int
    {
        $offset = $from;

        while (true) {
            $position = strpos($buffer, $firstDelimiter, $offset);

            if (false === $position) {
                return null;
            }

            if (0 === $position || (2 <= $position && self::CRLF === substr($buffer, $position - 2, 2))) {
                return $position;
            }

            $offset = $position + 1;
        }
    }

    /**
     * @param self::TAIL_CLOSE|self::TAIL_DELIMITER_LINE $tailOnLine
     * @return self::TAIL_*
     */
    private function paddedLineTail(string $buffer, int $from, int $tailOnLine): int
    {
        $length = strlen($buffer);
        $cursor = $from;

        while ($cursor < $length && str_contains(self::LWSP_CHARACTERS, $buffer[$cursor])) {
            ++$cursor;
        }

        if ($cursor >= $length) {
            return self::TAIL_INCOMPLETE;
        }

        if ("\r" !== $buffer[$cursor]) {
            return self::TAIL_NOT_DELIMITER;
        }

        if ($cursor + 1 >= $length) {
            return self::TAIL_INCOMPLETE;
        }

        if ("\n" !== $buffer[$cursor + 1]) {
            return self::TAIL_NOT_DELIMITER;
        }

        return $tailOnLine;
    }

    /**
     * @return self::TAIL_*
     */
    private function delimiterTail(string $buffer, int $from): int
    {
        $length = strlen($buffer);

        if ($from >= $length) {
            return self::TAIL_INCOMPLETE;
        }

        if (self::CLOSE_MARKER === substr($buffer, $from, 2)) {
            return $this->paddedLineTail($buffer, $from + 2, self::TAIL_CLOSE);
        }

        if ($from + 1 === $length) {
            return self::TAIL_INCOMPLETE;
        }

        return $this->paddedLineTail($buffer, $from, self::TAIL_DELIMITER_LINE);
    }

    private function endsWithLenientClose(string $buffer, int $from): bool
    {
        $length = strlen($buffer);

        if (self::CLOSE_MARKER !== substr($buffer, $from, 2)) {
            return false;
        }

        $cursor = $from + 2;

        while ($cursor < $length && str_contains(self::LWSP_CHARACTERS, $buffer[$cursor])) {
            ++$cursor;
        }

        return $cursor === $length;
    }

    private function pendingDelimiterPosition(string $buffer, string $delimiter, int $from): ?int
    {
        $position = strpos($buffer, $delimiter, $from);

        return false === $position ? null : $position;
    }

    /**
     * @return array{headers: string, content: string}|null
     */
    private function part(string $raw): ?array
    {
        $trimmed = trim($raw);

        if ('' === $trimmed || self::CLOSE_MARKER === $trimmed) {
            return null;
        }

        $segments = explode(self::CRLF . self::CRLF, $raw, 2);

        if (2 !== count($segments)) {
            return null;
        }

        return [
            'headers' => $segments[0],
            'content' => $segments[1],
        ];
    }
}
