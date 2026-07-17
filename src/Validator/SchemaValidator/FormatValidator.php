<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function is_array;
use function is_string;
use function sprintf;

final readonly class FormatValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorDependencies $dependencies,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->format || null === $schema->type) {
            return;
        }

        if (null === $data) {
            return;
        }

        if (is_array($schema->type)) {
            $type = $this->firstStringType($schema->type);

            if (null === $type) {
                return;
            }
        } else {
            $type = $schema->type;
        }

        $formatValidator = $this->dependencies->formatRegistry->getValidator($type, $schema->format);

        if (null === $formatValidator) {
            if ($this->dependencies->strictFormats) {
                throw new InvalidFormatException(
                    $schema->format,
                    $data,
                    sprintf('Unknown format "%s" for type "%s" in strict mode', $schema->format, $type),
                );
            }

            return;
        }

        $formatValidator->validate($data);
    }

    /**
     * @param list<string|null> $types
     */
    private function firstStringType(array $types): ?string
    {
        foreach ($types as $type) {
            if (is_string($type)) {
                return $type;
            }
        }

        return null;
    }
}
