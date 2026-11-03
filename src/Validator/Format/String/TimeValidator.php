<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function substr;

final readonly class TimeValidator extends AbstractStringFormatValidator
{
    private const string TIME_PATTERN = '/^'
        . '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d):(?<second>[0-5]\d|60)'
        . '(?:\.\d+)?'
        . '(?<offset>[Zz]|[+-](?:(?:[01]\d|2[0-3]):[0-5]\d))'
        . '\z/';

    private const int SECONDS_OF_LEAP_SECOND_MINUTE = 60;

    private const int MINUTES_BEFORE_LEAP_SECOND = 23 * 60 + 59;

    private const int MINUTES_PER_DAY = 24 * 60;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $matches = [];

        if (1 !== $this->pregExecutor->match(self::TIME_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('time', $data, 'Invalid time format');
        }

        if (self::SECONDS_OF_LEAP_SECOND_MINUTE !== (int) $matches['second']) {
            return;
        }

        if (false === $this->isUtcEndOfDay((string) $matches['hour'], (string) $matches['minute'], (string) $matches['offset'])) {
            throw new InvalidFormatException(
                'time',
                $data,
                'Leap second requires 23:59:60 UTC after offset normalization (RFC 3339)',
            );
        }
    }

    private function isUtcEndOfDay(string $hour, string $minute, string $offset): bool
    {
        $localMinutes = ((int) $hour) * 60 + (int) $minute;
        $offsetMinutes = $this->offsetMinutes($offset);
        $utcMinutes = ((($localMinutes - $offsetMinutes) % self::MINUTES_PER_DAY) + self::MINUTES_PER_DAY)
            % self::MINUTES_PER_DAY;

        return self::MINUTES_BEFORE_LEAP_SECOND === $utcMinutes;
    }

    private function offsetMinutes(string $offset): int
    {
        if ('Z' === $offset || 'z' === $offset) {
            return 0;
        }

        $sign = '+' === $offset[0] ? 1 : -1;

        return $sign * (((int) substr($offset, 1, 2)) * 60 + (int) substr($offset, 4, 2));
    }
}
