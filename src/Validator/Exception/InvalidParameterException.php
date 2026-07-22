<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

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
            sprintf('Invalid parameter "%s": %s', $parameterName, $message),
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
        return new self(
            $parameterName,
            'Malformed value: ' . $message,
            $code,
            $previous,
        );
    }

    /**
     * Returns the attacker-controlled parameter name (e.g. from a query
     * string). Pass $reveal = true only from trusted operator code; the
     * default returns '<redacted>' to prevent reflective serialization
     * from leaking attacker probes verbatim into logs.
     */
    public function parameterName(bool $reveal = false): string
    {
        return $reveal ? $this->parameterName : '<redacted>';
    }
}
