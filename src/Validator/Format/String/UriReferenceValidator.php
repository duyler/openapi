<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class UriReferenceValidator extends AbstractStringFormatValidator
{
    private const string RELATIVE_REF_PATTERN = '~^(?:'
        . '//[^\s/?#]*(?:/[^\s?#]*)?'
        . '|'
        . '/?[^\s?#]*'
        . ')(?:\?[^\s#]*)?(?:\#[^\s]*)?$~u';

    private readonly UriValidator $inner;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->inner = new UriValidator($this->pregExecutor);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'uri-reference';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if ($this->isAbsoluteUri($data)) {
            return;
        }

        if (1 !== $this->pregExecutor->match(self::RELATIVE_REF_PATTERN, $data)) {
            throw new InvalidFormatException('uri-reference', $data, 'Invalid URI reference format');
        }
    }

    private function isAbsoluteUri(string $data): bool
    {
        try {
            $this->inner->validate($data);

            return true;
        } catch (InvalidFormatException) {
            return false;
        }
    }
}
