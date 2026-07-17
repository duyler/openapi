<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

final readonly class AnyOfValidator extends AbstractCompositionalValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->anyOf && [] !== $schema->anyOf;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->anyOf) {
            return;
        }

        $result = $this->validateSchemas($schema->anyOf, $data, $context, 'anyOf');

        if (0 === $result->validCount) {
            throw new ValidationException(
                'At least one of the schemas must match, but none did',
                errors: $result->abstractErrors,
            );
        }
    }
}
