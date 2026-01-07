<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

class UnsupportedMediaTypeException extends RuntimeException
{
    /**
     * @param list<string> $supportedTypes
     */
    public function __construct(
        public readonly string $mediaType,
        public readonly array $supportedTypes,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Unsupported media type "%s". Supported types: %s',
                $mediaType,
                implode(', ', $supportedTypes),
            ),
            $code,
            $previous,
        );
    }
}
