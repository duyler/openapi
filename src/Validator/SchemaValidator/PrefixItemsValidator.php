<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function count;
use function gettype;
use function is_array;
use function sprintf;

final readonly class PrefixItemsValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->prefixItems || [] === $schema->prefixItems) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();

        $count = min(count($data), count($schema->prefixItems));

        for ($i = 0; $i < $count; ++$i) {
            try {
                $allowNull = $nullableAsType && ($schema->prefixItems[$i]->nullable
                    || SchemaValueNormalizer::typeIncludesNull($schema->prefixItems[$i]->type));
                $value = SchemaValueNormalizer::normalize($data[$i], $allowNull);

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumbIndex($i);

                try {
                    $validator->validate($value, $schema->prefixItems[$i], $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            } catch (InvalidDataTypeException $e) {
                $dataPath = $this->getDataPath($context);

                throw new ValidationException(
                    sprintf('Item at index %d has invalid data type: %s', $i, $e->getMessage()),
                    previous: $e,
                    errors: [
                        new TypeMismatchError(
                            expected: $this->formatSchemaType($schema->prefixItems[$i]->type),
                            actual: gettype($data[$i]),
                            dataPath: $dataPath . '[' . $i . ']',
                            schemaPath: '/prefixItems/' . $i,
                        ),
                    ],
                );
            } catch (InvalidFormatException $e) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $dataPath = $this->getDataPath($context);

                throw new ValidationException(
                    sprintf('Item at index %d validation failed: %s', $i, $e->getMessage()),
                    previous: $e,
                    errors: [$e],
                );
            } catch (ValidationException $e) {
                $dataPath = $this->getDataPath($context);
                $errors = $e->getErrors();

                if ([] === $errors) {
                    $errors = [
                        new NestedValidationError(
                            dataPath: $dataPath . '[' . $i . ']',
                            schemaPath: '/prefixItems/' . $i,
                            message: $e->getMessage(),
                        ),
                    ];
                }

                throw new ValidationException(
                    sprintf('Item at index %d validation failed', $i),
                    previous: $e,
                    errors: $errors,
                );
            }
        }
    }
}
