<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;

use function sprintf;

final class InvalidSchemaException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const int MAX_KEYWORD_LENGTH_IN_EXCEPTION = 128;

    public static function forUnknownKeyword(string $keyword, string $pointer): self
    {
        return new self(sprintf(
            "Unknown schema keyword '%s' at %s (register a keyword validator for it, allow-list it, or disable strict keywords)",
            LogContextSanitizer::truncate($keyword, self::MAX_KEYWORD_LENGTH_IN_EXCEPTION),
            LogContextSanitizer::truncate($pointer, self::MAX_KEYWORD_LENGTH_IN_EXCEPTION),
        ));
    }
}
