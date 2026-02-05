<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;

abstract readonly class AbstractStringFormatValidator implements FormatValidatorInterface
{
    #[Override]
    final public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException(
                $this->getFormatName(),
                $data,
                'Value must be a string',
            );
        }

        $this->validateString($data);
    }

    abstract protected function getFormatName(): string;

    abstract protected function validateString(string $data): void;
}
