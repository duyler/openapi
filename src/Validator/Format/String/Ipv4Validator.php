<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;

use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

final readonly class Ipv4Validator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('ipv4', $data, 'Value must be a string');
        }

        $filtered = filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if (false === $filtered) {
            throw new InvalidFormatException('ipv4', $data, 'Invalid IPv4 address format');
        }
    }
}
