<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class InvalidContentTypeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const string MESSAGE = 'Encoding contentType must be a string or a non-empty list of strings';

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forEncoding(): self
    {
        return new self(self::MESSAGE);
    }
}
