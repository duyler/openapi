<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class UriReferenceValidator extends AbstractStringFormatValidator
{
    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
        private readonly UriGrammar $uriGrammar = new UriGrammar(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri-reference';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === $this->uriGrammar->isUriReference($data, false)) {
            throw new InvalidFormatException('uri-reference', $data, 'Invalid URI reference format');
        }
    }
}
