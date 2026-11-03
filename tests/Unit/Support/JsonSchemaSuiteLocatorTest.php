<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Support;

use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_file;
use function is_string;
use function realpath;
use function str_starts_with;

final class JsonSchemaSuiteLocatorTest extends TestCase
{
    #[Test]
    public function suite_file_resolves_inside_suite_fixture_root(): void
    {
        $suiteFile = JsonSchemaSuiteLocator::suiteFile('draft2020-12/type.json');

        self::assertTrue(str_starts_with($suiteFile, JsonSchemaSuiteLocator::suiteFile('')));
        self::assertTrue(is_file($suiteFile));
    }

    #[Test]
    public function draft_directory_contains_type_section(): void
    {
        self::assertTrue(is_file(JsonSchemaSuiteLocator::draftDirectory() . '/type.json'));
    }

    #[Test]
    public function remotes_directory_contains_integer_remote(): void
    {
        self::assertTrue(is_file(JsonSchemaSuiteLocator::remotesDirectory() . '/integer.json'));
    }

    #[Test]
    public function remote_fixture_path_maps_localhost_uri_to_vendored_remote(): void
    {
        $remotePath = JsonSchemaSuiteLocator::remoteFixturePath('http://localhost:1234/integer.json');

        self::assertTrue(is_string($remotePath));
        self::assertTrue(is_file($remotePath));
        self::assertTrue(str_starts_with($remotePath, (string) realpath(JsonSchemaSuiteLocator::remotesDirectory()) . '/'));
    }

    #[Test]
    public function remote_fixture_path_normalizes_relative_segments(): void
    {
        $directPath = JsonSchemaSuiteLocator::remoteFixturePath('http://localhost:1234/integer.json');
        $normalizedPath = JsonSchemaSuiteLocator::remoteFixturePath('http://localhost:1234/baseUriChange/../integer.json');

        self::assertSame($directPath, $normalizedPath);
    }

    #[Test]
    public function remote_fixture_path_returns_null_for_foreign_uri(): void
    {
        self::assertNull(JsonSchemaSuiteLocator::remoteFixturePath('https://example.com/integer.json'));
    }

    #[Test]
    public function remote_fixture_path_returns_null_for_base_uri_without_path(): void
    {
        self::assertNull(JsonSchemaSuiteLocator::remoteFixturePath(JsonSchemaSuiteLocator::REMOTE_BASE_URI));
    }

    #[Test]
    public function remote_fixture_path_returns_null_for_traversal_to_missing_target(): void
    {
        self::assertNull(JsonSchemaSuiteLocator::remoteFixturePath('http://localhost:1234/../../outside-suite.json'));
    }

    #[Test]
    public function remote_fixture_path_returns_null_for_traversal_to_existing_file_outside_remotes(): void
    {
        self::assertNull(JsonSchemaSuiteLocator::remoteFixturePath('http://localhost:1234/../VERSION'));
    }
}
