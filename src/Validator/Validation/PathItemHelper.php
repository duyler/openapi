<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Operation as OperationModel;
use Duyler\OpenApi\Schema\Model\PathItem;

final readonly class PathItemHelper
{
    public static function getOperation(PathItem $pathItem, string $method): ?OperationModel
    {
        $method = strtolower($method);

        $standardOperation = $pathItem->getOperation($method);

        if (null !== $standardOperation) {
            return $standardOperation;
        }

        if (null !== $pathItem->additionalOperations) {
            foreach ($pathItem->additionalOperations as $opMethod => $operation) {
                if (strtolower($opMethod) === $method) {
                    return $operation;
                }
            }
        }

        return null;
    }
}
