<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\NotValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

final readonly class NotValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->not;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->not) {
            return;
        }

        // Boolean schema form per JSON Schema 2020-12 §4.3.2.
        // `not: false` always passes (the subschema never matches).
        if (false === $schema->not) {
            return;
        }

        // `not: true` always fails (the subschema matches every value).
        if (true === $schema->not) {
            throw new ValidationException(
                'Data must NOT match the "not" schema',
                errors: [
                    new NotValidationError(
                        dataPath: $this->getDataPath($context),
                        schemaPath: '/not',
                    ),
                ],
            );
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();
        $childContext = null !== $context ? $context->forkForBranch() : null;

        try {
            $allowNull = $nullableAsType && ($schema->not->nullable
                || SchemaValueNormalizer::typeIncludesNull($schema->not->type));
            $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
            $validator->validate($normalizedData, $schema->not, $childContext);
        } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
            return;
        }

        throw new ValidationException(
            'Data must NOT match the "not" schema',
            errors: [
                new NotValidationError(
                    dataPath: $this->getDataPath($context),
                    schemaPath: '/not',
                ),
            ],
        );
    }
}
