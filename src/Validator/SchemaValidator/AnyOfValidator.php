<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class AnyOfValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->anyOf) {
            return;
        }

        $validCount = 0;
        $errors = [];
        $abstractErrors = [];

        foreach ($schema->anyOf as $subSchema) {
            try {
                $normalizedData = SchemaValueNormalizer::normalize($data);
                $validator = new SchemaValidator($this->pool);
                $validator->validate($normalizedData, $subSchema, $context);
                ++$validCount;
            } catch (InvalidDataTypeException $e) {
                $errors[] = new ValidationException(
                    sprintf('Invalid data type for anyOf schema: %s', $e->getMessage()),
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
                'At least one of the schemas must match, but none did',
                errors: $abstractErrors,
            );
        }
    }
}
