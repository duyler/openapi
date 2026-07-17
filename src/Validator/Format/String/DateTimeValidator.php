<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateMalformedStringException;
use DateTimeImmutable;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function checkdate;
use function preg_match;

final readonly class DateTimeValidator extends AbstractStringFormatValidator
{
    /**
     * ISO 8601 / RFC 3339 date-time pattern.
     *
     * Enforces YYYY-MM-DD with leading zeros, HH:MM:SS in the 24-hour range,
     * accepts the leap second value 60 for the seconds field per RFC 3339 §4.2.3,
     * allows optional fractional seconds of arbitrary precision per RFC 3339 §2
     * and a mandatory timezone suffix. The timezone is either Z, z, or an
     * offset ±HH:MM bounded exactly to +14:00 / -14:00 per RFC 3339 §4.1
     * (offsets between -13:59 and +13:59 are accepted with any minute value,
     * but the boundary hour 14 requires minute 00). Semantic validity of the
     * date portion is verified separately via checkdate() to keep the
     * validator free of any process-global state and safe for long-running
     * runtimes (RoadRunner, FrankenPHP, Swoole). After the regex and
     * checkdate() pass, the DateTimeImmutable constructor performs the
     * final semantic check (covering arbitrary fractional precision that
     * DateTimeImmutable::createFromFormat with the `v` format cannot parse).
     */
    private const string DATETIME_PATTERN = '/^'
        . '(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>0[1-9]|[12]\d|3[01])'
        . '[Tt]'
        . '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d):(?<second>[0-5]\d|60)'
        . '(?:\.\d+)?'
        . '(?:[Zz]|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))'
        . '$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'date-time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $matches = [];

        if (1 !== preg_match(self::DATETIME_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('date-time', $data, 'Invalid date-time format');
        }

        if (false === checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            throw new InvalidFormatException('date-time', $data, 'Invalid date-time value');
        }

        if (60 === (int) $matches['second']) {
            if (23 === (int) $matches['hour'] && 59 === (int) $matches['minute']) {
                return;
            }

            throw new InvalidFormatException('date-time', $data, 'Invalid date-time value');
        }

        try {
            new DateTimeImmutable($data);
        } catch (DateMalformedStringException) {
            throw new InvalidFormatException('date-time', $data, 'Invalid date-time value');
        }
    }
}
