<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function count;

final readonly class AllOfValidator extends AbstractCompositionalValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->allOf && [] !== $schema->allOf;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->allOf) {
            return;
        }

        $result = $this->validateSchemas($schema->allOf, $data, $context, 'allOf');

        if ([] !== $result->errors || [] !== $result->abstractErrors) {
            $allErrors = $result->abstractErrors;

            foreach ($result->errors as $exception) {
                foreach ($exception->getErrors() as $error) {
                    $allErrors[] = $error;
                }
            }

            throw new ValidationException(
                'All of the schemas must match, but ' . count($result->errors) . ' failed',
                errors: $allErrors,
            );
        }
    }
}
