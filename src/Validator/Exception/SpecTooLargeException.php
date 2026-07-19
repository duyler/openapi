<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Thrown when an OpenAPI spec payload exceeds the configured size or nesting
 * depth limit (YamlParser). Defends against billion-laughs-style YAML that can
 * cause stack overflow or OOM during parsing. The size check runs BEFORE parse
 * so an attacker cannot force the parser to materialise an oversized document.
 */
final class SpecTooLargeException extends RuntimeException
{
    public static function forSize(int $max, int $actual, int $code = 0, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Spec payload of %d bytes exceeds the configured maximum of %d bytes',
                $actual,
                $max,
            ),
            $code,
            $previous,
        );
    }

    public static function forDepth(int $actual, int $max, int $code = 0, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Spec nesting depth of %d exceeds the configured maximum of %d',
                $actual,
                $max,
            ),
            $code,
            $previous,
        );
    }
}
