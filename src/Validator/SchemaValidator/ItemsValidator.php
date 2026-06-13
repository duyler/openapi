<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function is_array;
use function sprintf;
use function count;

final readonly class ItemsValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->items) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
        $validator = $this->createSchemaValidator();

        foreach ($data as $index => $item) {
            /** @var int $index */
            if ($index < $prefixCount) {
                continue;
            }

            $nullableAsType = $context?->nullableAsType ?? true;

            try {
                $allowNull = $schema->items->nullable && $nullableAsType;
                $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool, nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumbIndex($index);

                try {
                    $validator->validate($normalizedItem, $schema->items, $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            } catch (InvalidDataTypeException $e) {
                throw new ValidationException(
                    sprintf('Item at index %d has invalid data type: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                throw new ValidationException(
                    sprintf('Item at index %d validation failed', $index),
                    previous: $e,
                );
            }
        }
    }
}
