<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

final readonly class DateTimeValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'date-time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $dateTime = DateTime::createFromFormat(DateTime::RFC3339_EXTENDED, $data);

        if (false === $dateTime) {
            $dateTime = DateTime::createFromFormat(DateTime::RFC3339, $data);
        }

        if (false === $dateTime) {
            throw new InvalidFormatException('date-time', $data, 'Invalid date-time format');
        }

        $errors = DateTime::getLastErrors();

        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidFormatException('date-time', $data, 'Invalid date-time value');
        }
    }
}
