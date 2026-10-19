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
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ItemValidationExceptionTrait;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ItemValidationState;
use Override;

use function count;
use function is_array;

final readonly class PrefixItemsValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    use ItemValidationExceptionTrait;

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->prefixItems && [] !== $schema->prefixItems;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->prefixItems || [] === $schema->prefixItems) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $state = new ItemValidationState(
            itemsSchema: $schema,
            validator: $this->createSchemaValidator(),
            allowNull: false,
            nullableAsType: $context?->nullableAsType ?? true,
            context: $context,
        );

        $count = min(count($data), count($schema->prefixItems));

        for ($i = 0; $i < $count; ++$i) {
            $this->validatePrefixItemAt($data[$i], $i, $state);
        }
    }

    private function validatePrefixItemAt(mixed $item, int $index, ItemValidationState $state): void
    {
        $subSchema = $state->itemsSchema->prefixItems[$index] ?? null;

        if (null === $subSchema) {
            return;
        }

        try {
            $allowNull = SchemaValueNormalizer::allowsNull($subSchema, $state->nullableAsType);
            $value = SchemaValueNormalizer::normalize($item, $allowNull);

            if (null === $state->context) {
                $state->context = ValidationContext::create(pool: $this->pool(), nullableAsType: $state->nullableAsType);
            }

            $state->context->enterBreadcrumbIndex($index);

            try {
                $state->validator->validate($value, $subSchema, $state->context);
                $state->context->markItemEvaluated($index);
            } finally {
                $state->context->leaveBreadcrumb();
            }
        } catch (InvalidDataTypeException|InvalidFormatException|AbstractValidationError|ValidationException $e) {
            $this->wrapItemValidationException(
                $e,
                $index,
                $item,
                $subSchema,
                '/prefixItems/' . $index,
                $state->context,
            );
        }
    }
}
