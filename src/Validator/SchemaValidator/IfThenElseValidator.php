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

final readonly class IfThenElseValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->if;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->if) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();

        $ifValid = $this->validateIfBranch($validator, $data, $schema->if, $context, $nullableAsType);

        if ($ifValid && null !== $schema->then) {
            $this->validateThenOrElse($validator, $data, $schema->then, $context, $nullableAsType);

            return;
        }

        if (false === $ifValid && null !== $schema->else) {
            $this->validateThenOrElse($validator, $data, $schema->else, $context, $nullableAsType);
        }
    }

    private function validateIfBranch(
        SchemaValidator $validator,
        mixed $data,
        Schema $subSchema,
        ?ValidationContext $context,
        bool $nullableAsType,
    ): bool {
        if (null === $context) {
            try {
                $normalized = $this->normalizeFor($data, $subSchema, $nullableAsType);
                $validator->validate($normalized, $subSchema, null);

                return true;
            } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
                return false;
            }
        }

        $childContext = $context->forkForBranch();

        try {
            $normalized = $this->normalizeFor($data, $subSchema, $nullableAsType);
            $validator->validate($normalized, $subSchema, $childContext);
        } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
            return false;
        }

        $context->mergeChildAnnotations($childContext);

        return true;
    }

    private function validateThenOrElse(
        SchemaValidator $validator,
        mixed $data,
        Schema $subSchema,
        ?ValidationContext $context,
        bool $nullableAsType,
    ): void {
        $normalized = $this->normalizeFor($data, $subSchema, $nullableAsType);

        if (null === $context) {
            $validator->validate($normalized, $subSchema, null);

            return;
        }

        $childContext = $context->forkForBranch();
        $validator->validate($normalized, $subSchema, $childContext);
        $context->mergeChildAnnotations($childContext);
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool|null
     */
    private function normalizeFor(mixed $data, Schema $subSchema, bool $nullableAsType): array|int|string|float|bool|null
    {
        $allowNull = $nullableAsType && ($subSchema->nullable
            || SchemaValueNormalizer::typeIncludesNull($subSchema->type));

        return SchemaValueNormalizer::normalize($data, $allowNull);
    }
}
