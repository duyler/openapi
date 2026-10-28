<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function sprintf;
use function str_contains;
use function strlen;
use function substr;
use function substr_count;

final readonly class UriTemplateValidator extends AbstractStringFormatValidator
{
    private const int MAX_EXPRESSIONS = 1000;

    private const string OPERATOR_SET = '+#./;?&=,!@|';

    private const string LITERAL_PATTERN = '/^(?:[\x21\x23\x24\x26\x27\x28-\x3B\x3D\x3F-\x5B\x5D\x5F\x61-\x7A\x7E]'
        . '|%[0-9A-Fa-f]{2}'
        . '|[\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}'
        . '\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}'
        . '\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}'
        . '\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}'
        . '\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}]'
        . '|[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}])/u';

    private const string VARCHAR_PATTERN = '/^(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri-template';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (substr_count($data, '{') > self::MAX_EXPRESSIONS) {
            throw new InvalidFormatException(
                'uri-template',
                $data,
                sprintf('URI template exceeds maximum expression count (%d)', self::MAX_EXPRESSIONS),
            );
        }

        $length = strlen($data);
        $position = 0;

        while ($position < $length) {
            if ('{' === $data[$position]) {
                $syntaxError = false;
                $nextPosition = $this->expressionEnd($data, $position, $syntaxError);

                if (false === $nextPosition) {
                    throw new InvalidFormatException(
                        'uri-template',
                        $data,
                        true === $syntaxError ? 'Invalid URI template' : 'Unbalanced template expressions',
                    );
                }

                $position = $nextPosition;

                continue;
            }

            $literalLength = $this->literalLength($data, $position);

            if (0 === $literalLength) {
                throw new InvalidFormatException('uri-template', $data, 'Invalid URI template');
            }

            $position += $literalLength;
        }
    }

    private function literalLength(string $data, int $position): int
    {
        if (1 === $this->pregExecutor->match(self::LITERAL_PATTERN, substr($data, $position), $matches)) {
            return strlen((string) $matches[0]);
        }

        return 0;
    }

    private function expressionEnd(string $data, int $openPosition, bool &$syntaxError): int|false
    {
        $length = strlen($data);
        $position = $openPosition + 1;

        if ($position >= $length) {
            return false;
        }

        if (true === str_contains(self::OPERATOR_SET, $data[$position])) {
            $position++;
        }

        $position = $this->varListEnd($data, $position);

        if (false === $position) {
            $syntaxError = true;

            return false;
        }

        if ($position >= $length) {
            return false;
        }

        if ('}' !== $data[$position]) {
            $syntaxError = true;

            return false;
        }

        return $position + 1;
    }

    private function varListEnd(string $data, int $startPosition): int|false
    {
        $position = $startPosition;
        $length = strlen($data);

        while (true) {
            $position = $this->varSpecEnd($data, $position);

            if (false === $position) {
                return false;
            }

            if ($position >= $length) {
                return $position;
            }

            if (',' === $data[$position]) {
                $position++;

                continue;
            }

            return $position;
        }
    }

    private function varSpecEnd(string $data, int $startPosition): int|false
    {
        $position = $this->varNameEnd($data, $startPosition);

        if (false === $position || $position === $startPosition) {
            return false;
        }

        $length = strlen($data);

        if ($position < $length && ':' === $data[$position]) {
            return $this->maxLengthEnd($data, $position + 1);
        }

        if ($position < $length && '*' === $data[$position]) {
            return $position + 1;
        }

        return $position;
    }

    private function varNameEnd(string $data, int $startPosition): int|false
    {
        $position = $this->varcharEnd($data, $startPosition);

        if (false === $position) {
            return false;
        }

        $length = strlen($data);

        while ($position < $length) {
            if ('.' === $data[$position]) {
                $nextPosition = $this->varcharEnd($data, $position + 1);

                if (false === $nextPosition) {
                    return false;
                }

                $position = $nextPosition;

                continue;
            }

            $nextPosition = $this->varcharEnd($data, $position);

            if (false === $nextPosition) {
                break;
            }

            $position = $nextPosition;
        }

        return $position;
    }

    private function varcharEnd(string $data, int $position): int|false
    {
        $length = strlen($data);

        if ($position >= $length) {
            return false;
        }

        if (1 === $this->pregExecutor->match(self::VARCHAR_PATTERN, substr($data, $position), $matches)) {
            return $position + strlen((string) $matches[0]);
        }

        return false;
    }

    private function maxLengthEnd(string $data, int $startPosition): int|false
    {
        $length = strlen($data);
        $position = $startPosition;

        if ($position >= $length || !ctype_digit($data[$position]) || '0' === $data[$position]) {
            return false;
        }

        $position++;

        $digitCount = 1;

        while ($position < $length && $digitCount < 4 && ctype_digit($data[$position])) {
            $position++;
            $digitCount++;
        }

        if ($position < $length && ctype_digit($data[$position])) {
            return false;
        }

        return $position;
    }
}
