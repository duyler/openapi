<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class PrefixItemsValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->prefixItems || [] === $schema->prefixItems) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $validator = new SchemaValidator($this->pool);

        $count = min(count($data), count($schema->prefixItems));

        for ($i = 0; $i < $count; ++$i) {
            try {
                $value = SchemaValueNormalizer::normalize($data[$i]);
                $indexContext = $context?->withBreadcrumbIndex($i) ?? ValidationContext::create($this->pool);
                $validator->validate($value, $schema->prefixItems[$i], $indexContext);
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

        $remainingItems = array_slice($data, $count);

        if ([] !== $remainingItems && null !== $schema->items) {
            foreach ($remainingItems as $item) {
                try {
                    $normalizedItem = SchemaValueNormalizer::normalize($item);
                    $validator->validate($normalizedItem, $schema->items, $context);
                } catch (InvalidDataTypeException $e) {
                    throw new ValidationException(
                        sprintf('Remaining item has invalid data type: %s', $e->getMessage()),
                        previous: $e,
                    );
                } catch (ValidationException $e) {
                    throw new ValidationException(
                        'Remaining item validation failed',
                        previous: $e,
                    );
                }
            }
        }
    }
}
