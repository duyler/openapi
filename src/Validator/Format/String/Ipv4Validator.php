<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

readonly class Ipv4Validator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'ipv4';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $filtered = filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if (false === $filtered) {
            throw new InvalidFormatException('ipv4', $data, 'Invalid IPv4 address format');
        }
    }
}
