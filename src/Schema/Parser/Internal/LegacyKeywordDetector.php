<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function array_key_exists;

/** @internal */
final readonly class LegacyKeywordDetector
{
    /** @var array<string, string> */
    public const array MIGRATION_TARGETS = [
        '$recursiveRef' => '$dynamicRef',
        '$recursiveAnchor' => '$dynamicAnchor',
    ];

    /** @return array<string, string> */
    public function detect(Schema $schema): array
    {
        $detected = [];

        foreach (self::MIGRATION_TARGETS as $keyword => $migrationTarget) {
            if (array_key_exists($keyword, $schema->extras ?? [])) {
                $detected[$keyword] = $migrationTarget;
            }
        }

        return $detected;
    }
}
