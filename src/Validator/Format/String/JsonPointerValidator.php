<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class JsonPointerValidator extends AbstractStringFormatValidator
{
    private const string POINTER_PATTERN = '/^(?:\/(?:[^~\/]|~[01])*)*$/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'json-pointer';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if ('' === $data || '/' === $data) {
            return;
        }

        if (1 !== $this->pregExecutor->match(self::POINTER_PATTERN, $data)) {
            throw new InvalidFormatException('json-pointer', $data, 'Invalid JSON Pointer format');
        }
    }
}
