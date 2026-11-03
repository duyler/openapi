<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;

use function sprintf;

final class VocabularyNotSupportedException extends RuntimeException
{
    use SanitizableExceptionTrait;

    private const int MAX_URI_LENGTH_IN_EXCEPTION = 256;

    public readonly string $vocabularyUri;

    public function __construct(string $vocabularyUri)
    {
        $this->vocabularyUri = LogContextSanitizer::truncate($vocabularyUri, self::MAX_URI_LENGTH_IN_EXCEPTION);

        parent::__construct(
            sprintf('Document requires unsupported vocabulary: %s', $this->vocabularyUri),
        );
    }
}
