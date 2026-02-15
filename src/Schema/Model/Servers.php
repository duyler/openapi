<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Servers implements JsonSerializable
{
    /**
     * @param list<Server> $servers
     */
    public function __construct(
        public array $servers,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        return ['servers' => array_map(fn($server) => $server->jsonSerialize(), $this->servers)];
    }
}
