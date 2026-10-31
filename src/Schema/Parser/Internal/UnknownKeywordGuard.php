<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalkerInterface;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorRegistry;
use WeakMap;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function str_starts_with;
use function strval;

/** @internal */
final readonly class UnknownKeywordGuard
{
    private const string VENDOR_PREFIX = 'x-';

    /** @var array<string, true> */
    private readonly array $allowedUnknownKeywordSet;

    /**
     * @param list<string> $allowedUnknownKeywords
     */
    public function __construct(
        private readonly SchemaSubschemaWalkerInterface $walker = new SchemaSubschemaWalker(),
        private readonly KeywordValidatorRegistry $registry = new KeywordValidatorRegistry(),
        array $allowedUnknownKeywords = [],
        private readonly bool $strictKeywords = true,
        private readonly DocumentSchemaCollector $documentSchemaCollector = new DocumentSchemaCollector(),
    ) {
        $this->allowedUnknownKeywordSet = array_fill_keys($allowedUnknownKeywords, true);
    }

    public function guard(OpenApiDocument $document): void
    {
        if (false === $this->strictKeywords) {
            return;
        }

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();

        foreach ($this->documentSchemaCollector->collectWithLocations($document) as $location) {
            $this->scanSchema($location, $visited);
        }
    }

    /**
     * @param WeakMap<Schema, true> $visited
     */
    private function scanSchema(SchemaLocation $location, WeakMap $visited): void
    {
        $schema = $location->schema;

        if (isset($visited[$schema])) {
            return;
        }

        $visited[$schema] = true;

        foreach (array_map(strval(...), array_keys($schema->extras ?? [])) as $keyword) {
            if ($this->isLegal($keyword)) {
                continue;
            }

            throw InvalidSchemaException::forUnknownKeyword($keyword, $location->pointer);
        }

        foreach ($this->walker->iterateSubSchemas($schema) as $segment => $subSchema) {
            $this->scanSchema(
                new SchemaLocation($subSchema, $location->pointer . '/' . $segment),
                $visited,
            );
        }
    }

    private function isLegal(string $keyword): bool
    {
        if (str_starts_with($keyword, self::VENDOR_PREFIX)) {
            return true;
        }

        return KnownSchemaKeywords::isKnown($keyword)
            || OasBaseVocabulary::isKnown($keyword)
            || array_key_exists($keyword, LegacyKeywordDetector::MIGRATION_TARGETS)
            || $this->registry->has($keyword)
            || isset($this->allowedUnknownKeywordSet[$keyword]);
    }
}
