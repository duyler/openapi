<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function file_get_contents;
use function is_array;
use function json_decode;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

final class JsonSchemaSuiteCaseLoader
{
    /** @return list<string> */
    public static function sections(string $directory): array
    {
        $sections = [];

        foreach (self::sectionFiles($directory) as $filePath) {
            $sections[] = self::sectionName($directory, $filePath);
        }

        sort($sections, SORT_STRING);

        return $sections;
    }

    /** @return list<JsonSchemaSuiteCase> */
    public static function loadDirectory(string $directory): array
    {
        $cases = [];

        foreach (self::sectionFiles($directory) as $filePath) {
            $section = self::sectionName($directory, $filePath);
            $groups = self::decodeGroups($filePath);

            foreach ($groups as $group) {
                foreach ($group['tests'] as $test) {
                    $cases[] = new JsonSchemaSuiteCase(
                        section: $section,
                        description: sprintf('%s / %s', $group['description'], $test['description']),
                        schema: $group['schema'],
                        instance: $test['data'],
                        expectedValid: $test['valid'],
                    );
                }
            }
        }

        return $cases;
    }

    /** @return list<string> */
    private static function sectionFiles(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo instanceof SplFileInfo && 'json' === $fileInfo->getExtension()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private static function sectionName(string $directory, string $filePath): string
    {
        $relativePath = substr($filePath, strlen($directory) + 1);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    /** @return list<array{description: string, schema: mixed, tests: list<array{description: string, data: mixed, valid: bool}>}> */
    private static function decodeGroups(string $filePath): array
    {
        $decoded = json_decode((string) file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);

        if (false === is_array($decoded)) {
            throw new RuntimeException(sprintf('Suite section is not a JSON array of groups: %s', $filePath));
        }

        return $decoded;
    }
}
