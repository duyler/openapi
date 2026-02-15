<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

readonly class IfThenElseValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->if) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $allowNull = $schema->if->nullable && $nullableAsType;
        $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
        $ifValid = true;
        try {
            $validator = new SchemaValidator($this->pool);
            $validator->validate($normalizedData, $schema->if, $context);
        } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
            $ifValid = false;
        }

        if (true === $ifValid && null !== $schema->then) {
            $validator = new SchemaValidator($this->pool);
            $validator->validate($normalizedData, $schema->then, $context);
            return;
        }

        if (false === $ifValid && null !== $schema->else) {
            $validator = new SchemaValidator($this->pool);
            $validator->validate($normalizedData, $schema->else, $context);
        }
    }
}
