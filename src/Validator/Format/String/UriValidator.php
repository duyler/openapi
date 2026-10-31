<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

final readonly class UriValidator extends AbstractStringFormatValidator
{
    public function __construct(
        private readonly UriGrammar $uriGrammar = new UriGrammar(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === $this->uriGrammar->isAbsoluteUri($data, false)) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }
    }
}
