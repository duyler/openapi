<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use RuntimeException;

use function sprintf;

final class InvalidAnchorException extends RuntimeException
{
    public function __construct(string $keyword, string $anchor)
    {
        parent::__construct(sprintf(
            'Invalid %s value "%s": anchor name must match ^[A-Za-z_][-A-Za-z0-9._]*$ (JSON Schema 2020-12 core §8.2.3)',
            $keyword,
            $anchor,
        ));
    }
}
