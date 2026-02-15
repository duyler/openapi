<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Override;
use RuntimeException;
use Throwable;

use function sprintf;

final class UnresolvableRefException extends RuntimeException
{
    public function __construct(
        public readonly string $ref,
        public readonly string $reason,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $message = sprintf(
            'Cannot resolve $ref "%s": %s',
            $ref,
            $reason,
        );

        parent::__construct($message, $code, $previous);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
