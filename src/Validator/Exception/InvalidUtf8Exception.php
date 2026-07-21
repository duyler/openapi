<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

/**
 * Thrown when input that must be UTF-8 per RFC 8259 §8.1 contains byte
 * sequences that are not valid UTF-8. PHP's json_decode() does not enforce
 * UTF-8 validity by default, so JsonBodyParser and JsonParser reject such
 * input via mb_check_encoding() before decoding.
 */
final class InvalidUtf8Exception extends RuntimeException
{
    use SanitizableExceptionTrait;
}
