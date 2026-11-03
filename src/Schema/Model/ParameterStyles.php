<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

final readonly class ParameterStyles
{
    public static function defaultFor(string $in): string
    {
        return match ($in) {
            'path' => 'simple',
            'query' => 'form',
            'header' => 'simple',
            'cookie' => 'form',
            default => 'form',
        };
    }
}
