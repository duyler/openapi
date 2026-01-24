<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;
use function strlen;

final readonly class HostnameValidator implements FormatValidatorInterface
{
    private const string HOSTNAME_PATTERN = '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
    private const int MAX_HOSTNAME_LENGTH = 253;
    private const int MAX_LABEL_LENGTH = 63;

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('hostname', $data, 'Value must be a string');
        }

        if (strlen($data) > self::MAX_HOSTNAME_LENGTH) {
            throw new InvalidFormatException('hostname', $data, 'Hostname must not exceed 253 characters');
        }

        if (1 !== preg_match(self::HOSTNAME_PATTERN, $data)) {
            throw new InvalidFormatException('hostname', $data, 'Invalid hostname format');
        }

        $labels = explode('.', $data);
        foreach ($labels as $label) {
            if (strlen($label) > self::MAX_LABEL_LENGTH) {
                throw new InvalidFormatException('hostname', $data, 'Each label must not exceed 63 characters');
            }

            if (str_starts_with($label, '-') || str_ends_with($label, '-')) {
                throw new InvalidFormatException('hostname', $data, 'Labels must not start or end with hyphen');
            }
        }
    }
}
