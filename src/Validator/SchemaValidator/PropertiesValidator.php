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

final readonly class PropertiesValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $validator = new SchemaValidator($this->pool);

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $data)) {
                continue;
            }

            try {
                $value = SchemaValueNormalizer::normalize($data[$name]);
                $propertyContext = $context?->withBreadcrumb($name) ?? ValidationContext::create($this->pool);
                $validator->validate($value, $propertySchema, $propertyContext);
            } catch (InvalidDataTypeException $e) {
                throw new ValidationException(
                    sprintf('Property "%s" has invalid data type: %s', $name, $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                throw new ValidationException(
                    sprintf('Property "%s" validation failed', $name),
                    previous: $e,
                );
            }
        }
    }
}
