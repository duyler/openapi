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
use function is_bool;

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

        if (is_bool($schema->propertyNames) && $schema->propertyNames) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        if (false === $schema->propertyNames) {
            $this->rejectAllPropertyNames($data, $dataPath);

            return;
        }

        /** @var Schema $propertyNamesSchema */
        $propertyNamesSchema = $schema->propertyNames;
        $this->validateEachPropertyName($data, $propertyNamesSchema, $context);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function rejectAllPropertyNames(array $data, string $dataPath): void
    {
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
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function validateEachPropertyName(array $data, Schema $propertyNamesSchema, ?ValidationContext $context): void
    {
        if (null !== $propertyNamesSchema->pattern && '' !== $propertyNamesSchema->pattern) {
            $regexValidator = $this->regexValidator();
            $regexValidator->validate(
                $regexValidator->normalize($propertyNamesSchema->pattern),
                'propertyNames pattern',
            );
        }

        $validator = $this->createSchemaValidator();

        foreach (array_keys($data) as $propertyName) {
            $validator->validate($propertyName, $propertyNamesSchema, $context);
        }
    }
}
