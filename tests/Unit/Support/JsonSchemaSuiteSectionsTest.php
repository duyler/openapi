<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Support;

use Duyler\OpenApi\Test\Integration\JsonSchemaSuite\JsonSchemaSuiteTest;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteCaseLoader;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_intersect;
use function array_keys;
use function count;
use function implode;
use function sprintf;

final class JsonSchemaSuiteSectionsTest extends TestCase
{
    #[Test]
    public function every_vendored_section_is_classified_exactly_once(): void
    {
        $vendoredSections = JsonSchemaSuiteCaseLoader::sections(JsonSchemaSuiteLocator::draftDirectory());
        $activeSections = JsonSchemaSuiteTest::ACTIVE_SECTIONS;
        $pendingSections = array_keys(JsonSchemaSuiteTest::PENDING_SECTIONS);
        $excludedSections = JsonSchemaSuiteTest::EXCLUDED_SECTIONS;

        $unclassified = array_diff($vendoredSections, $activeSections, $pendingSections, $excludedSections);

        self::assertSame(
            [],
            $unclassified,
            sprintf('Sections missing classification: %s', implode(', ', $unclassified)),
        );

        $overlappingSections = [
            ...array_intersect($activeSections, $pendingSections),
            ...array_intersect($activeSections, $excludedSections),
            ...array_intersect($pendingSections, $excludedSections),
        ];

        self::assertSame(
            [],
            $overlappingSections,
            sprintf('Sections present in several lists: %s', implode(', ', $overlappingSections)),
        );

        $classifiedTotal = count($activeSections) + count($pendingSections) + count($excludedSections);

        self::assertCount($classifiedTotal, $vendoredSections);
    }

    #[Test]
    public function classification_references_only_existing_sections(): void
    {
        $vendoredSections = JsonSchemaSuiteCaseLoader::sections(JsonSchemaSuiteLocator::draftDirectory());

        $listedSections = [
            ...JsonSchemaSuiteTest::ACTIVE_SECTIONS,
            ...array_keys(JsonSchemaSuiteTest::PENDING_SECTIONS),
            ...JsonSchemaSuiteTest::EXCLUDED_SECTIONS,
        ];

        $unknownSections = array_diff($listedSections, $vendoredSections);

        self::assertSame(
            [],
            $unknownSections,
            sprintf('Classified sections absent in vendored suite: %s', implode(', ', $unknownSections)),
        );
    }

    #[Test]
    public function pending_holders_reference_existing_chain_tasks(): void
    {
        $pendingSections = JsonSchemaSuiteTest::PENDING_SECTIONS;

        foreach ($pendingSections as $section => $holderTask) {
            self::assertMatchesRegularExpression(
                '#^FS-(0[2-9]|1[0-9]|2[0-5]|3[0-9]|40)$#',
                $holderTask,
                sprintf('Section %s has invalid holder task %s', $section, $holderTask),
            );
        }
    }
}
