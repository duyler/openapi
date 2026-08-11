<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;

use function sprintf;
use function is_bool;

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

        if ($this->acceptsNullAsNullable($data, $schema, $context)) {
            return;
        }

        if (is_bool($schema->if)) {
            $this->routeThenOrElse(schema: $schema, data: $data, context: $context, ifValid: $schema->if);

            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();
        $ifValid = $this->validateIfBranch($validator, $data, $schema->if, $context, $nullableAsType);

        $this->routeThenOrElse(schema: $schema, data: $data, context: $context, ifValid: $ifValid, validator: $validator, nullableAsType: $nullableAsType);
    }

    private function routeThenOrElse(
        Schema $schema,
        mixed $data,
        ?ValidationContext $context,
        bool $ifValid,
        ?SchemaValidatorInterface $validator = null,
        bool $nullableAsType = true,
    ): void {
        if ($ifValid) {
            $this->applyBranch($schema->then, $data, $context, $validator, $nullableAsType, 'then');

            return;
        }

        $this->applyBranch($schema->else, $data, $context, $validator, $nullableAsType, 'else');
    }

    private function applyBranch(Schema|bool|null $branch, mixed $data, ?ValidationContext $context, ?SchemaValidatorInterface $validator, bool $nullableAsType, string $keyword): void
    {
        if (null === $branch) {
            return;
        }

        if ($branch instanceof Schema) {
            $this->validateThenOrElse($validator ?? $this->createSchemaValidator(), $data, $branch, $context, $nullableAsType);

            return;
        }

        $this->applyBooleanBranch($branch, $data, $context, $keyword);
    }

    private function applyBooleanBranch(Schema|bool $branch, mixed $data, ?ValidationContext $context, string $keyword): void
    {
        if (is_bool($branch) && $branch) {
            return;
        }

        if (false === $branch) {
            $dataPath = $this->getDataPath($context);

            throw new ValidationException(
                sprintf('Data rejected by boolean-%s branch', $keyword),
                errors: [
                    new TypeMismatchError(
                        expected: 'nothing (boolean schema false)',
                        actual: TypeFormatter::format($data),
                        dataPath: $dataPath,
                        schemaPath: '/' . $keyword,
                    ),
                ],
            );
        }
    }

    private function validateIfBranch(
        SchemaValidatorInterface $validator,
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
        SchemaValidatorInterface $validator,
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
            || SchemaValueNormalizer::doesTypeIncludeNull($subSchema->type));

        return SchemaValueNormalizer::normalize($data, $allowNull);
    }
}
