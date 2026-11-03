<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Support;

use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCaseLoader;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function is_bool;
use function is_string;
use function sprintf;

final class JsonSchemaSuiteCaseLoaderTest extends TestCase
{
    #[Test]
    public function sections_list_every_vendored_suite_file(): void
    {
        $sections = JsonSchemaSuiteCaseLoader::sections(JsonSchemaSuiteLocator::draftDirectory());

        self::assertContains('type.json', $sections);
        self::assertContains('optional/ecmascript-regex.json', $sections);
        self::assertGreaterThan(70, count($sections));
    }

    #[Test]
    public function load_directory_returns_cases_with_section_and_expectation(): void
    {
        $cases = JsonSchemaSuiteCaseLoader::loadDirectory(JsonSchemaSuiteLocator::draftDirectory());

        self::assertNotEmpty($cases);

        $typeCases = [];

        foreach ($cases as $case) {
            if ('type.json' === $case->section) {
                $typeCases[] = $case;
            }
        }

        self::assertNotEmpty($typeCases);

        $firstCase = $typeCases[0];

        self::assertSame('type.json', $firstCase->section);
        self::assertTrue(is_string($firstCase->description));
        self::assertTrue(is_bool($firstCase->expectedValid));
        self::assertNotSame('', $firstCase->description);
    }

    #[Test]
    public function load_directory_is_deterministic_between_runs(): void
    {
        $firstRun = JsonSchemaSuiteCaseLoader::loadDirectory(JsonSchemaSuiteLocator::draftDirectory());
        $secondRun = JsonSchemaSuiteCaseLoader::loadDirectory(JsonSchemaSuiteLocator::draftDirectory());

        $caseIdentities = [];

        foreach ($firstRun as $index => $case) {
            $caseIdentities[$index] = $case->section . '|' . $case->description;
        }

        foreach ($secondRun as $index => $case) {
            self::assertSame(
                $caseIdentities[$index],
                $case->section . '|' . $case->description,
                sprintf('Case order drifted at index %d', $index),
            );
        }

        self::assertCount(count($secondRun), $caseIdentities);
    }
}
