<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;

final readonly class TimeValidator extends AbstractStringFormatValidator
{
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

        if (
            60 === (int) $matches['second']
            && (23 !== (int) $matches['hour'] || 59 !== (int) $matches['minute'])
        ) {
            throw new InvalidFormatException('time', $data, 'Invalid time value');
        }
    }
}
