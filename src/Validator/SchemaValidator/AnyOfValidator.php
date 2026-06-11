<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

final readonly class AnyOfValidator extends AbstractCompositionalValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->anyOf) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;

        if (null === $data && $nullableAsType) {
            $hasNullableSchema = array_any($schema->anyOf, fn($subSchema) => $subSchema->nullable);
            if ($hasNullableSchema) {
                return;
            }
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
