<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

final readonly class JsonSchemaSuiteCase
{
    public function __construct(
        public string $section,
        public string $description,
        public mixed $schema,
        public mixed $instance,
        public bool $expectedValid,
    ) {}
}
