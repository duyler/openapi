<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

readonly class DependentSchemasValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->dependentSchemas || [] === $schema->dependentSchemas) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;

        foreach ($schema->dependentSchemas as $propertyName => $dependentSchema) {
            if (array_key_exists($propertyName, $data)) {
                try {
                    $allowNull = $dependentSchema->nullable && $nullableAsType;
                    $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                    $validator = new SchemaValidator($this->pool);
                    $validator->validate($normalizedData, $dependentSchema, $context);
                } catch (InvalidDataTypeException $e) {
                    throw new ValidationException(
                        sprintf('Dependent schema for property "%s" has invalid data type: %s', $propertyName, $e->getMessage()),
                        previous: $e,
                    );
                } catch (ValidationException $e) {
                    throw new ValidationException(
                        sprintf('Dependent schema for property "%s" validation failed', $propertyName),
                        previous: $e,
                    );
                }
            }
        }
    }
}
