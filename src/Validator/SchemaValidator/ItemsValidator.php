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

        $validator = new SchemaValidator($this->pool);

        foreach ($data as $index => $item) {
            /** @var int $index */
            try {
                $nullableAsType = $context?->nullableAsType ?? true;
                $allowNull = $schema->items->nullable && $nullableAsType;
                $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);
                $itemContext = $context?->withBreadcrumbIndex($index) ?? ValidationContext::create($this->pool, $nullableAsType);
                $validator->validate($normalizedItem, $schema->items, $itemContext);
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
