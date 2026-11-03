<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

final readonly class IdnHostnameValidator extends AbstractStringFormatValidator
{
    public function __construct(
        private readonly IdnProcessor $idnProcessor = new IdnProcessor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'idn-hostname';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === $this->idnProcessor->isInternationalizedHostname($data)) {
            throw new InvalidFormatException('idn-hostname', $data, 'Invalid idn-hostname format');
        }
    }
}
