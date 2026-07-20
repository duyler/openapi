<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Override;
use RuntimeException;
use Throwable;

final class UnresolvableRefException extends RuntimeException
{
    public function __construct(
        public readonly string $ref,
        public readonly string $reason,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $internalTrace = null,
    ) {
        parent::__construct($reason, $code, $previous);
    }

    /**
     * Returns only the safe reason; suppresses class name, file path, and stack trace
     * emitted by default Exception::__toString() to prevent PSR-15 middleware leaks.
     */
    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
