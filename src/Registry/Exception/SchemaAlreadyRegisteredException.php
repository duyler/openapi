<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Registry\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;
use Throwable;

use function sprintf;

final class SchemaAlreadyRegisteredException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $name,
        public readonly string $version,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Schema "%s" version "%s" is already registered. Use registerOrReplace() for explicit overwrite.', $name, $version),
            $code,
            $previous,
        );
    }
}
