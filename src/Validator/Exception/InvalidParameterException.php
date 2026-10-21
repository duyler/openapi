<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

final class InvalidParameterException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        protected readonly string $parameterName,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'Invalid parameter configuration',
            $code,
            $previous,
        );
    }

    public static function invalidConfiguration(string $parameterName, string $message, int $code = 0, ?Throwable $previous = null): self
    {
        return new self($parameterName, $message, $code, $previous);
    }

    public static function malformedValue(string $parameterName, string $message, int $code = 0, ?Throwable $previous = null): self
    {
        return new self($parameterName, $message, $code, $previous);
    }

    public function parameterName(bool $reveal = false): string
    {
        return $reveal ? $this->parameterName : '<redacted>';
    }
}
