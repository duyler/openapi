<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

final class StreamRecordTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly int $maxBytes,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forStreamLine(int $maxBytes): self
    {
        return new self(
            $maxBytes,
            sprintf(
                'Stream line exceeds maximum allowed length of %d bytes',
                $maxBytes,
            ),
        );
    }

    public static function forJsonSequenceRecord(int $maxBytes): self
    {
        return new self(
            $maxBytes,
            sprintf(
                'JSON sequence record exceeds maximum allowed length of %d bytes',
                $maxBytes,
            ),
        );
    }
}
