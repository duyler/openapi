<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

final class FixtureLocator
{
    private const string ROOT = __DIR__ . '/../Fixture';

    public static function path(string $relative): string
    {
        return self::ROOT . '/' . $relative;
    }
}
