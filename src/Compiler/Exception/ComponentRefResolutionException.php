<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

final class ComponentRefResolutionException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $ref,
        public readonly ?string $schemaName,
    ) {
        parent::__construct(
            null === $schemaName
                ? sprintf('Unsupported $ref: %s', $ref)
                : sprintf('Schema not found: %s', $schemaName),
        );
    }
}
