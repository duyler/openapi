<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class DateValidator implements FormatValidatorInterface
{
    private const string DATE_FORMAT = 'Y-m-d';

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('date', $data, 'Value must be a string');
        }

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
