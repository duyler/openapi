<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

/** @internal */
final class ChainDepthMemo
{
    /** @var array<string, int> */
    private array $depths = [];

    public function get(string $node): ?int
    {
        return $this->depths[$node] ?? null;
    }

    public function set(string $node, int $depth): void
    {
        $this->depths[$node] = $depth;
    }
}
