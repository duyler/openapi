<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use function count;

/**
 * Immutable breadcrumb for tracking validation error paths
 *
 * Uses JSON Pointer format (RFC 6901) for path representation.
 * Example: ["users", "0", "name"] -> "/users/0/name"
 */
readonly class Breadcrumb
{
    /**
     * @param array<int, string> $segments
     */
    public function __construct(
        private readonly array $segments = [],
    ) {}

    public function append(string $segment): self
    {
        $newSegments = [...$this->segments, $segment];

        return new self($newSegments);
    }

    public function appendIndex(int $index): self
    {
        return $this->append((string) $index);
    }

    /**
     * Convert to JSON Pointer format
     *
     * Example: ["users", "0", "name"] -> "/users/0/name"
     */
    public function toString(): string
    {
        if ([] === $this->segments) {
            return '/';
        }

        return '/' . implode('/', $this->segments);
    }

    public function current(): ?string
    {
        $lastIndex = array_key_last($this->segments);

        if (null === $lastIndex) {
            return null;
        }

        return $this->segments[$lastIndex];
    }

    public function depth(): int
    {
        return count($this->segments);
    }

    /**
     * @return array<int, string>
     */
    public function segments(): array
    {
        return $this->segments;
    }
}
