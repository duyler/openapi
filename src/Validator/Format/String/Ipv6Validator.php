<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class Ipv6Validator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('ipv6', $data, 'Value must be a string');
        }

        $filtered = filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

        if (false === $filtered) {
            throw new InvalidFormatException('ipv6', $data, 'Invalid IPv6 address format');
        }
    }
}
