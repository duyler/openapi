<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function assert;

final class PathRegexCache
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function getOrCompute(string $template): string
    {
        if (isset(self::$cache[$template])) {
            return self::$cache[$template];
        }

        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $template);

        assert(null !== $pattern);

        $regex = '#^' . $pattern . '$#';

        self::$cache[$template] = $regex;

        return $regex;
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}
