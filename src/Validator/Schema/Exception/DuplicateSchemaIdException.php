<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use RuntimeException;

use function sprintf;

final class DuplicateSchemaIdException extends RuntimeException
{
    public function __construct(string $uri)
    {
        parent::__construct(sprintf(
            'Duplicate schema $id: "%s" is already registered by a different schema instance',
            $uri,
        ));
    }
}
