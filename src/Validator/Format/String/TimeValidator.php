<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;

final readonly class TimeValidator implements FormatValidatorInterface
{
    private const string TIME_FORMAT = 'H:i:s';

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('time', $data, 'Value must be a string');
        }

        $time = DateTime::createFromFormat(self::TIME_FORMAT, substr($data, 0, 8));

        if (false === $time) {
            throw new InvalidFormatException('time', $data, 'Invalid time format');
        }

        $errors = DateTime::getLastErrors();

        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidFormatException('time', $data, 'Invalid time value');
        }

        $remaining = substr($data, 8);
        if ($remaining === '') {
            return;
        }

        if ($remaining === 'Z') {
            return;
        }

        if (preg_match('/^(\+|-)\d{2}:\d{2}$/', $remaining)) {
            return;
        }

        if (preg_match('/^\.\d+([Z]|(\+|-)\d{2}:\d{2})?$/', $remaining)) {
            return;
        }

        throw new InvalidFormatException('time', $data, 'Invalid time format');
    }
}
