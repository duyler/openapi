<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;
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

        // Boolean schema form per JSON Schema 2020-12 §4.3.2.
        // `propertyNames: true` accepts every property name (no-op).
        if (true === $schema->propertyNames) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        // `propertyNames: false` rejects every property name.
        if (false === $schema->propertyNames) {
            $errors = [];

            foreach (array_keys($data) as $propertyName) {
                $keyString = (string) $propertyName;
                $errors[] = new TypeMismatchError(
                    expected: 'nothing (boolean schema false)',
                    actual: TypeFormatter::format($keyString),
                    dataPath: $dataPath,
                    schemaPath: '/propertyNames',
                );
            }

            if ([] !== $errors) {
                throw new ValidationException(
                    'Property names rejected by propertyNames: false',
                    errors: $errors,
                );
            }

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
