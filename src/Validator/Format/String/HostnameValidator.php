<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

final readonly class HostnameValidator extends AbstractStringFormatValidator
{
    public function __construct(
        private readonly IdnProcessor $idnProcessor = new IdnProcessor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'hostname';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === $this->idnProcessor->isAsciiHostname($data)) {
            throw new InvalidFormatException('hostname', $data, 'Invalid hostname format');
        }
    }
}
