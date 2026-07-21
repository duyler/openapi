<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class RegexFormatValidator extends AbstractStringFormatValidator
{
    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'regex';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        try {
            $result = $this->pregExecutor->match('~' . $data . '~u', '');
        } catch (PregRuntimeException) {
            throw new InvalidFormatException('regex', $data, 'Invalid regular expression pattern');
        }

        if (false === $result) {
            throw new InvalidFormatException('regex', $data, 'Invalid regular expression pattern');
        }
    }
}
