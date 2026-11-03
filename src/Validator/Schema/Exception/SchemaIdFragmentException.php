<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use RuntimeException;

use function sprintf;

final class SchemaIdFragmentException extends RuntimeException
{
    public function __construct(string $uri)
    {
        parent::__construct(sprintf(
            'Schema $id must not contain a fragment: "%s" (JSON Schema 2020-12 core §8.2.1)',
            $uri,
        ));
    }
}
