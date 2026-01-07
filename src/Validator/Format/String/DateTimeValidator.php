<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class DateTimeValidator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('date-time', $data, 'Value must be a string');
        }

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
