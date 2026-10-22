<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use function preg_quote;
use function preg_replace;
use function realpath;
use function str_starts_with;

final class JsonSchemaSuiteLocator
{
    public const string REMOTE_BASE_URI = 'http://localhost:1234/';

    public static function suiteFile(string $relativePath): string
    {
        return FixtureLocator::path('JsonSchemaTestSuite/' . $relativePath);
    }

    public static function draftDirectory(): string
    {
        return self::suiteFile('draft2020-12');
    }

    public static function remotesDirectory(): string
    {
        return self::suiteFile('remotes');
    }

    public static function remoteFixturePath(string $uri): ?string
    {
        $escapedBaseUri = preg_quote(self::REMOTE_BASE_URI, '#');
        $relativePath = preg_replace('#^' . $escapedBaseUri . '#', '', $uri);

        if (null === $relativePath || $relativePath === $uri) {
            return null;
        }

        $realRemotePath = realpath(self::remotesDirectory() . '/' . $relativePath);

        if (false === $realRemotePath) {
            return null;
        }

        $realRemotesDirectory = realpath(self::remotesDirectory());

        if (false === $realRemotesDirectory || false === str_starts_with($realRemotePath, $realRemotesDirectory . '/')) {
            return null;
        }

        return $realRemotePath;
    }
}
