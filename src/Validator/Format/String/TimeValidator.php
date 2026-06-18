<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;

final readonly class TimeValidator extends AbstractStringFormatValidator
{
    /**
     * ISO 8601 / RFC 3339 time pattern.
     *
     * Enforces HH:MM:SS in the 24-hour range, accepts the leap second value
     * 60 for the seconds field per RFC 3339 §4.2.3, allows optional fractional
     * seconds of arbitrary precision per RFC 3339 §2 and an optional timezone
     * suffix. The timezone is either Z, z, or an offset ±HH:MM bounded exactly
     * to +14:00 / -14:00 per RFC 3339 §4.1 (offsets between -13:59 and +13:59
     * are accepted with any minute value, but the boundary hour 14 requires
     * minute 00). A leap second is semantically valid only as 23:59:60 per
     * RFC 3339 §4.2.3; any other placement of the seconds value 60 is
     * rejected after the regex match.
     */
    private const string TIME_PATTERN = '/^'
        . '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d):(?<second>[0-5]\d|60)'
        . '(?:\.\d+)?'
        . '(?:[Zz]|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))?'
        . '$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $matches = [];

        if (1 !== preg_match(self::TIME_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('time', $data, 'Invalid time format');
        }

        if (60 === (int) $matches['second']) {
            if (23 === (int) $matches['hour'] && 59 === (int) $matches['minute']) {
                return;
            }

            throw new InvalidFormatException('time', $data, 'Invalid time value');
        }
    }
}
