<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Override;
use Stringable;

use function count;
use function sprintf;

final readonly class Operation implements Stringable
{
    private readonly int $placeholderCount;

    public function __construct(
        public readonly string $path,
        public readonly string $method,
    ) {
        preg_match_all('/\{[^}]+\}/', $this->path, $matches);

        $this->placeholderCount = count($matches[0]);
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s %s', strtoupper($this->method), $this->path);
    }

    public function countPlaceholders(): int
    {
        return $this->placeholderCount;
    }
}
