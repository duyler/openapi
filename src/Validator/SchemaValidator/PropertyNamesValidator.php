<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function is_array;

final readonly class PropertyNamesValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->propertyNames;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->propertyNames) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        if (array_is_list($data)) {
            return;
        }

        if (null !== $schema->propertyNames->pattern && '' !== $schema->propertyNames->pattern) {
            $regexValidator = $this->regexValidator();
            $regexValidator->validate(
                $regexValidator->normalize($schema->propertyNames->pattern),
                'propertyNames pattern',
            );
        }

        $validator = $this->createSchemaValidator();

        foreach (array_keys($data) as $propertyName) {
            $validator->validate($propertyName, $schema->propertyNames, $context);
        }
    }
}
