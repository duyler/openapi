<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function sprintf;

final readonly class OneOfValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->oneOf) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;

        if (null === $data && $nullableAsType) {
            $hasNullableSchema = array_any($schema->oneOf, fn($subSchema) => $subSchema->nullable);
            if ($hasNullableSchema) {
                return;
            }
        }

        $validCount = 0;
        $errors = [];
        $abstractErrors = [];

        foreach ($schema->oneOf as $subSchema) {
            try {
                $allowNull = $subSchema->nullable && $nullableAsType;
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $validator = new SchemaValidator($this->pool);
                $validator->validate($normalizedData, $subSchema, $context);
                ++$validCount;
            } catch (InvalidDataTypeException $e) {
                $errors[] = new ValidationException(
                    sprintf('Invalid data type for oneOf schema: %s', $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                $errors[] = $e;
                $abstractErrors = [...$abstractErrors, ...$e->getErrors()];
            } catch (AbstractValidationError $e) {
                $abstractErrors[] = $e;
            }
        }

        if (0 === $validCount) {
            throw new ValidationException(
                'Exactly one of the schemas must match, but none did',
                errors: $abstractErrors,
            );
        }

        if ($validCount > 1) {
            $dataPath = $this->getDataPath($context);
            throw new OneOfError(
                dataPath: $dataPath,
                schemaPath: '/oneOf',
            );
        }
    }
}
