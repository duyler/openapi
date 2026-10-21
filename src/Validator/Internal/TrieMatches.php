<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Duyler\OpenApi\Schema\Model\PathItem;

/** @internal */
final class TrieMatches
{
    /** @var list<array{template: string, item: PathItem}> */
    private array $items = [];

    /**
     * @param array{template: string, item: PathItem} $match
     */
    public function add(array $match): void
    {
        $this->items[] = $match;
    }

    /**
     * @return list<array{template: string, item: PathItem}>
     */
    public function all(): array
    {
        return $this->items;
    }
}
