<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Registry\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

final class VersionNotFoundException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(string $name, ?string $version = null)
    {
        if (null === $version) {
            parent::__construct(
                sprintf('Schema "%s" has no registered versions', $name),
            );

            return;
        }

        parent::__construct(
            sprintf('Schema "%s" does not have version "%s"', $name, $version),
        );
    }
}
