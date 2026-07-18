<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function checkdate;
use function preg_match;

final readonly class DateValidator extends AbstractStringFormatValidator
{
    private const string DATE_PATTERN = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>0[1-9]|[12]\d|3[01])$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'date';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $matches = [];

        if (1 !== preg_match(self::DATE_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('date', $data, 'Invalid date format');
        }

        if (false === checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            throw new InvalidFormatException('date', $data, 'Invalid date value');
        }
    }
}
