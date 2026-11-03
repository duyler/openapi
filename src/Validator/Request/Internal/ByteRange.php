<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidContentRangeException;

use function preg_match;

use const PREG_UNMATCHED_AS_NULL;

/** @internal */
final readonly class ByteRange
{
    private const string HEADER_PATTERN
        = '/^bytes (?:(?<first>\d+)-(?<last>\d+)\/(?<complete>\d+|\*)|\*\/(?<unsizedComplete>\d+))\z/';

    private function __construct(
        public ?int $first,
        public ?int $last,
        public ?int $complete,
    ) {}

    public static function fromHeader(string $header): self
    {
        if (1 !== preg_match(self::HEADER_PATTERN, $header, $matches, PREG_UNMATCHED_AS_NULL)) {
            throw InvalidContentRangeException::malformedSyntax();
        }

        $unsizedComplete = $matches['unsizedComplete'] ?? null;

        if (null !== $unsizedComplete) {
            return new self(first: null, last: null, complete: (int) $unsizedComplete);
        }

        $first = (int) ($matches['first'] ?? '0');
        $last = (int) ($matches['last'] ?? '0');
        $complete = $matches['complete'] ?? '0';

        if ($last < $first) {
            throw InvalidContentRangeException::invertedRange(first: $first, last: $last);
        }

        return new self(
            first: $first,
            last: $last,
            complete: '*' === $complete ? null : (int) $complete,
        );
    }
}
