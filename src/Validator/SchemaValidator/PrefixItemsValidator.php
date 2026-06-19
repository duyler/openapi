<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function count;
use function is_array;
use function sprintf;

final readonly class PrefixItemsValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->prefixItems || [] === $schema->prefixItems) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();

        $count = min(count($data), count($schema->prefixItems));

        for ($i = 0; $i < $count; ++$i) {
            try {
                $allowNull = $schema->prefixItems[$i]->nullable && $nullableAsType;
                $value = SchemaValueNormalizer::normalize($data[$i], $allowNull);

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool, nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumbIndex($i);

                try {
                    $validator->validate($value, $schema->prefixItems[$i], $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            } catch (InvalidDataTypeException $e) {
                throw new ValidationException(
                    sprintf('Item at index %d has invalid data type: %s', $i, $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                throw new ValidationException(
                    sprintf('Item at index %d validation failed', $i),
                    previous: $e,
                );
            }
        }
    }
}
