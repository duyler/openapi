<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

final readonly class DateValidator extends AbstractStringFormatValidator
{
    private const string DATE_FORMAT = 'Y-m-d';

    #[Override]
    protected function getFormatName(): string
    {
        return 'date';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $date = DateTime::createFromFormat(self::DATE_FORMAT, $data);

        if (false === $date) {
            throw new InvalidFormatException('date', $data, 'Invalid date format');
        }

        $errors = DateTime::getLastErrors();

        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidFormatException('date', $data, 'Invalid date value');
        }
    }
}
