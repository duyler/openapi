<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Exception;

use RuntimeException;

use function implode;
use function sprintf;

final class UnsupportedKeywordException extends RuntimeException
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public readonly array $keywords,
    ) {
        parent::__construct(
            sprintf(
                'Unsupported keywords in schema for compilation: %s. '
                . 'The ValidatorCompiler does not support these keywords yet.',
                implode(', ', $keywords),
            ),
        );
    }
}
