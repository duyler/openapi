<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Server;

final readonly class ResolvedLink
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public array $parameters,
        public mixed $requestBody,
        public ?Server $server,
    ) {}
}
