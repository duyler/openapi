<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class DecodedContentTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly int $actualBytes,
        public readonly int $maxBytes,
        public readonly ?string $encoding,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Decoded content of %d bytes exceeds the configured maximum of %d bytes',
                $actualBytes,
                $maxBytes,
            ),
            $code,
            $previous,
        );
    }
}
