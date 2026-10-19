<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

final readonly class ConfinementVerdict
{
    private function __construct() {}

    public static function clean(): self
    {
        return new self();
    }
}
