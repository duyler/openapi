<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

readonly class OneOfValidator extends AbstractCompositionalValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->oneOf) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;

        if (null === $data && $nullableAsType) {
            $hasNullableSchema = array_any($schema->oneOf, fn($subSchema) => $subSchema->nullable);
            if ($hasNullableSchema) {
                return;
            }
        }

        $result = $this->validateSchemas($schema->oneOf, $data, $context, 'oneOf');

        if (0 === $result->validCount) {
            throw new ValidationException(
                'Exactly one of the schemas must match, but none did',
                errors: $result->abstractErrors,
            );
        }

        if ($result->validCount > 1) {
            $dataPath = $this->getDataPath($context);
            throw new OneOfError(
                dataPath: $dataPath,
                schemaPath: '/oneOf',
            );
        }
    }
}
