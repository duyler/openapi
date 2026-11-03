<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
final class ChainResources
{
    /** @var list<Schema> */
    private array $resources = [];

    public function add(Schema $resource): void
    {
        $this->resources[] = $resource;
    }

    /** @return list<Schema> */
    public function resources(): array
    {
        return $this->resources;
    }
}
