<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Example;

final readonly class ExampleWarning
{
    public function __construct(
        public readonly string $data,
        public readonly string $message,
    ) {}
}
