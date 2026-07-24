<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

/**
 * Carries attacker-controlled parameter metadata without leaking it into
 * the rendered message. The constructor's $message argument is intentionally
 * discarded — getMessage() always returns the static string
 * 'Invalid parameter configuration' so a PSR-15 middleware cannot be
 * turned into a reflective XSS or log-injection sink by a crafted
 * parameter name (CWE-209, CWE-532).
 */
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

    /**
     * Returns the spec-defined parameter name. Pass $reveal = true only
     * from trusted operator code; the default returns '<redacted>' to
     * prevent reflective serialization from leaking attacker probes
     * verbatim into logs.
     */
    public function parameterName(bool $reveal = false): string
    {
        return $reveal ? $this->parameterName : '<redacted>';
    }
}
