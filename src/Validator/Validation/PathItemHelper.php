<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;

final readonly class PathItemHelper
{
    public static function getOperation(PathItem $pathItem, string $httpMethod): ?Operation
    {
        $httpMethod = strtolower($httpMethod);

        $standardOperation = $pathItem->getOperation($httpMethod);

        if (null !== $standardOperation) {
            return $standardOperation;
        }

        if (null !== $pathItem->additionalOperations) {
            foreach ($pathItem->additionalOperations as $opMethod => $operation) {
                if (strtolower($opMethod) === $httpMethod) {
                    return $operation;
                }
            }
        }

        return null;
    }
}
