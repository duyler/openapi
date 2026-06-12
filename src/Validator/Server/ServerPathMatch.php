<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;

final readonly class ServerPathMatch
{
    public function __construct(
        public string $strippedPath,
        public Server $matchedServer,
    ) {}
}
