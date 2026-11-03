<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\Internal\LegacyKeywordDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyKeywordDetector::class)]
final class LegacyKeywordDetectorTest extends TestCase
{
    #[Test]
    public function detect_maps_present_legacy_keywords_to_migration_targets(): void
    {
        $schema = new Schema(
            type: 'object',
            extras: [
                '$recursiveRef' => '#/x',
                '$recursiveAnchor' => true,
                'x-vendor' => ['a' => 1],
            ],
        );

        $detected = new LegacyKeywordDetector()->detect($schema);

        self::assertSame([
            '$recursiveRef' => '$dynamicRef',
            '$recursiveAnchor' => '$dynamicAnchor',
        ], $detected);
    }

    #[Test]
    public function detect_returns_empty_map_for_clean_schema(): void
    {
        $schema = new Schema(type: 'object', extras: ['x-vendor' => 'keep', 'requird' => ['a']]);

        $detected = new LegacyKeywordDetector()->detect($schema);

        self::assertSame([], $detected);
    }

    #[Test]
    public function detect_returns_empty_map_without_extras(): void
    {
        $schema = new Schema(type: 'object');

        $detected = new LegacyKeywordDetector()->detect($schema);

        self::assertSame([], $detected);
    }

    #[Test]
    public function detect_matches_extras_keys_byte_wise(): void
    {
        $schema = new Schema(type: 'object', extras: ['$RecursiveRef' => '#/x']);

        $detected = new LegacyKeywordDetector()->detect($schema);

        self::assertSame([], $detected);
    }
}
