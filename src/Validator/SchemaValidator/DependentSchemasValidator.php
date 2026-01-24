<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

final readonly class DependentSchemasValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->dependentSchemas || [] === $schema->dependentSchemas) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        foreach ($schema->dependentSchemas as $propertyName => $dependentSchema) {
            if (array_key_exists($propertyName, $data)) {
                try {
                    $normalizedData = SchemaValueNormalizer::normalize($data);
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
