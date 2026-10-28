<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;
use function sprintf;
use function strlen;

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

        try {
            $this->validateString($data);
        } catch (PregRuntimeException) {
            throw new InvalidFormatException(
                $this->getFormatName(),
                $data,
                sprintf('Invalid %s format', $this->getFormatName()),
            );
        }
    }

    abstract protected function getFormatName(): string;

    abstract protected function validateString(string $data): void;

    final protected function findQuotedStringEnd(string $data, bool $asciiOnly): ?int
    {
        $length = strlen($data);
        $position = 1;

        while ($position < $length) {
            $char = $data[$position];

            if ('\\' === $char) {
                if ($position + 1 >= $length) {
                    return null;
                }

                $escaped = $data[$position + 1];

                if ($escaped < ' ' || $escaped > '~') {
                    return null;
                }

                $position += 2;

                continue;
            }

            if ('"' === $char) {
                return $position;
            }

            if (true === $asciiOnly && ($char < ' ' || $char > '~')) {
                return null;
            }

            $position++;
        }

        return null;
    }
}
