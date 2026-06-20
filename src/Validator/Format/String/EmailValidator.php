<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function filter_var;
use function preg_match;
use function sprintf;
use function strlen;

use const FILTER_VALIDATE_EMAIL;

final readonly class EmailValidator extends AbstractStringFormatValidator
{
    private const string EMAIL_PATTERN = '/^(?<local>[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]{1,64})@(?<domain>(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,})$/';

    private const int MAX_EMAIL = 254;

    #[Override]
    protected function getFormatName(): string
    {
        return 'email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== preg_match(self::EMAIL_PATTERN, $data)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }

        if (strlen($data) > self::MAX_EMAIL) {
            throw new InvalidFormatException('email', $data, sprintf('Email exceeds RFC 5321 max length (%d)', self::MAX_EMAIL));
        }

        if (false === filter_var($data, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }
}
