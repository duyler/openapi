<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

final readonly class PropertiesValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->properties && [] !== $schema->properties;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $validator = $this->createSchemaValidator();

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $data)) {
                continue;
            }

            $nullableAsType = $context?->nullableAsType ?? true;

            try {
                $allowNull = $nullableAsType && ($propertySchema->nullable
                    || SchemaValueNormalizer::typeIncludesNull($propertySchema->type));
                $value = SchemaValueNormalizer::normalize($data[$name], $allowNull);

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumb($name);

                try {
                    $validator->validate($value, $propertySchema, $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            } catch (InvalidDataTypeException $e) {
                throw new ValidationException(
                    sprintf('Property "%s" has invalid data type: %s', $name, $e->getMessage()),
                    previous: $e,
                    errors: [$e],
                );
            } catch (ValidationException $e) {
                throw new ValidationException(
                    sprintf('Property "%s" validation failed', $name),
                    previous: $e,
                    errors: $e->getErrors(),
                );
            } catch (InvalidFormatException $e) {
                throw $e;
            } catch (AbstractValidationError $e) {
                throw new ValidationException(
                    sprintf('Property "%s" validation failed', $name),
                    previous: $e,
                    errors: [$e],
                );
            }
        }
    }
}
