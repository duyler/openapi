<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function strval;

use const ARRAY_FILTER_USE_KEY;

/** @internal */
final readonly class ExtrasSchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @return array{extras: ?array<string, mixed>}
     */
    public function build(array $data): array
    {
        $unknown = array_filter(
            $data,
            static fn(int|string $keyword): bool => false === KnownSchemaKeywords::isKnown((string) $keyword),
            ARRAY_FILTER_USE_KEY,
        );

        if ([] === $unknown) {
            return ['extras' => null];
        }

        return [
            'extras' => array_combine(
                array_map(strval(...), array_keys($unknown)),
                array_values($unknown),
            ),
        ];
    }
}
