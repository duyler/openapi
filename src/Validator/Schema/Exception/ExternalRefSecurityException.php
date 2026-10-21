<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;
use Throwable;

final class ExternalRefSecurityException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const int MAX_REF_LENGTH_IN_EXCEPTION = 256;

    protected readonly string $ref;

    public function __construct(
        string $ref,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $this->ref = LogContextSanitizer::truncate($ref, self::MAX_REF_LENGTH_IN_EXCEPTION);

        parent::__construct($message, $code, $previous);
    }

    public function ref(bool $reveal = false): string
    {
        return $reveal ? $this->ref : '<redacted>';
    }
}
