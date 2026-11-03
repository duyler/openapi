<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class TypeArrayNotSupportedException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const string MESSAGE = 'Type arrays are not allowed in OpenAPI 3.0 documents: the type keyword MUST be a string';

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forOpenApi30(): self
    {
        return new self(self::MESSAGE);
    }
}
