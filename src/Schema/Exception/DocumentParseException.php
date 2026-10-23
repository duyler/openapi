<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;
use Throwable;

use function sprintf;

final class DocumentParseException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const int MAX_URI_LENGTH_IN_EXCEPTION = 256;

    public readonly string $retrievalUri;

    public function __construct(string $retrievalUri, ?Throwable $previous = null)
    {
        $this->retrievalUri = LogContextSanitizer::truncate($retrievalUri, self::MAX_URI_LENGTH_IN_EXCEPTION);

        parent::__construct(
            sprintf('Failed to parse document with retrieval URI: %s', $this->retrievalUri),
            0,
            $previous,
        );
    }
}
