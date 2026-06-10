<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class SchemaDepthExceededException extends RuntimeException
{
    public function __construct(int $maxDepth)
    {
        parent::__construct(
            sprintf('Maximum schema depth of %d exceeded', $maxDepth),
        );
    }
}
