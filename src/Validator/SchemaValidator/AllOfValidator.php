<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function sprintf;

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

        if ($this->acceptsNullAsNullable($data, $schema, $context)) {
            return;
        }

        $result = $this->validateSchemas($schema->allOf, $data, $context, 'allOf');

        if (0 !== $result->failedCount) {
            throw new ValidationException(
                sprintf('All of the schemas must match, but %d failed', $result->failedCount),
                errors: $result->errors,
            );
        }
    }
}
