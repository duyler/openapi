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

        if (true === $schema->if) {
            $this->routeThenOrElse(schema: $schema, data: $data, context: $context, ifValid: true);

            return;
        }

        if (false === $schema->if) {
            $this->routeThenOrElse(schema: $schema, data: $data, context: $context, ifValid: false);

            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();
        $ifValid = $this->validateIfBranch($validator, $data, $schema->if, $context, $nullableAsType);

        $this->routeThenOrElse(schema: $schema, data: $data, context: $context, ifValid: $ifValid, validator: $validator, nullableAsType: $nullableAsType);
    }

    /**
     * Routes to the `then` or `else` branch based on the `if` validation
     * result. Handles both Schema-instance branches (delegated to the
     * recursive validator) and boolean branches per JSON Schema 2020-12
     * §4.3.2 (`true` always passes; `false` always rejects with a typed
     * `TypeMismatchError`).
     */
    private function routeThenOrElse(
        Schema $schema,
        mixed $data,
        ?ValidationContext $context,
        bool $ifValid,
        ?SchemaValidator $validator = null,
        bool $nullableAsType = true,
    ): void {
        if ($ifValid) {
            if (null === $schema->then) {
                return;
            }

            if ($schema->then instanceof Schema) {
                $this->validateThenOrElse($validator ?? $this->createSchemaValidator(), $data, $schema->then, $context, $nullableAsType);
            } else {
                $this->applyBooleanBranch($schema->then, $data, $context, 'then');
            }

            return;
        }

        if (null === $schema->else) {
            return;
        }

        if ($schema->else instanceof Schema) {
            $this->validateThenOrElse($validator ?? $this->createSchemaValidator(), $data, $schema->else, $context, $nullableAsType);
        } else {
            $this->applyBooleanBranch($schema->else, $data, $context, 'else');
        }
    }

    /**
     * Handles boolean-form `then` / `else` branches.
     *
     * `true` always passes (no-op); `false` always rejects with a
     * `TypeMismatchError` so the conditional routing surfaces a typed
     * failure rather than silently swallowing the rejection.
     */
    private function applyBooleanBranch(Schema|bool $branch, mixed $data, ?ValidationContext $context, string $keyword): void
    {
        if (true === $branch) {
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
