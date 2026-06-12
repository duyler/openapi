<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

final readonly class ValidationWarningEvent
{
    public function __construct(
        public readonly string $propertyPath = '',
        public readonly string $propertyName = '',
        public readonly string $message = '',
        public readonly ?string $schemaRef = null,
    ) {}
}
