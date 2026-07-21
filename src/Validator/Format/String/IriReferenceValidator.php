<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class IriReferenceValidator extends AbstractStringFormatValidator
{
    private readonly UriReferenceValidator $inner;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->inner = new UriReferenceValidator($this->pregExecutor);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'iri-reference';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        try {
            $this->inner->validate($data);
        } catch (InvalidFormatException $e) {
            throw new InvalidFormatException('iri-reference', $data, $e->getMessage());
        }
    }
}
