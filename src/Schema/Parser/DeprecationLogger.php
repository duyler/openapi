<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;

final readonly class DeprecationLogger
{
    public function __construct(private LoggerInterface $logger = new NullLogger(), private bool $enabled = true) {}

    public function warn(string $field, string $object, string $version, string $alternative = ''): void
    {
        if (false === $this->enabled) {
            return;
        }

        $message = sprintf(
            "Field '%s' in %s is deprecated since OpenAPI %s.",
            $field,
            $object,
            $version,
        );

        if ('' !== $alternative) {
            $message .= sprintf(" Use '%s' instead.", $alternative);
        }

        $this->logger->warning($message);
    }
}
