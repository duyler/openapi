<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;
use function substr;

readonly class TimeValidator extends AbstractStringFormatValidator
{
    private const string TIME_FORMAT = 'H:i:s';

    #[Override]
    protected function getFormatName(): string
    {
        return 'time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
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
