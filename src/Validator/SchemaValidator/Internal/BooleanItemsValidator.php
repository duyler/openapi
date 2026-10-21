<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;

use function count;

/** @internal */
final class BooleanItemsValidator
{
    private const string ITEMS_SCHEMA_PATH = '/items';

    /**
     * @param array<array-key, mixed> $data
     */
    public static function validate(array $data, Schema $schema, ?ValidationContext $context): void
    {
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;

        if ($schema->items) {
            $dataCount = count($data);

            if (null !== $context) {
                for ($i = $prefixCount; $i < $dataCount; ++$i) {
                    $context->markItemEvaluated($i);
                }
            }

            return;
        }

        $dataPath = null === $context ? '/' : $context->breadcrumbs->currentPath();
        $errors = [];
        $dataCount = count($data);

        for ($i = $prefixCount; $i < $dataCount; ++$i) {
            /** @var mixed $rejectedItem */
            $rejectedItem = $data[$i];

            $errors[] = new TypeMismatchError(
                expected: 'nothing (boolean schema false)',
                actual: TypeFormatter::format($rejectedItem),
                dataPath: $dataPath . '[' . $i . ']',
                schemaPath: self::ITEMS_SCHEMA_PATH,
            );
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Items rejected by items: false',
                errors: $errors,
            );
        }
    }
}
