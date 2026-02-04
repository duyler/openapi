<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final readonly class Ipv6Validator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'ipv6';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $filtered = filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

        if (false === $filtered) {
            throw new InvalidFormatException('ipv6', $data, 'Invalid IPv6 address format');
        }
    }
}
