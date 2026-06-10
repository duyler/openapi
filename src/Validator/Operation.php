<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Override;
use Stringable;

use function count;
use function sprintf;

readonly class Operation implements Stringable
{
    public function __construct(
        public readonly string $path,
        public readonly string $method,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s %s', strtoupper($this->method), $this->path);
    }

    public function countPlaceholders(): int
    {
        preg_match_all('/\{[^}]+\}/', $this->path, $matches);

        return count($matches[0] ?? []);
    }
}
