<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Event\LegacyKeywordWarningEvent;
use Duyler\OpenApi\Event\VocabularyDisabledWarningEvent;
use Duyler\OpenApi\Schema\Exception\VocabularyNotSupportedException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalkerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use WeakMap;

use function array_keys;
use function in_array;

/** @internal */
final readonly class VocabularyGuard
{
    /** @var list<string> */
    public const array SUPPORTED_VOCABULARY_URIS = [
        'https://json-schema.org/draft/2020-12/vocab/core',
        'https://json-schema.org/draft/2020-12/vocab/applicator',
        'https://json-schema.org/draft/2020-12/vocab/unevaluated',
        'https://json-schema.org/draft/2020-12/vocab/validation',
        'https://json-schema.org/draft/2020-12/vocab/meta-data',
        'https://json-schema.org/draft/2020-12/vocab/format-annotation',
        'https://json-schema.org/draft/2020-12/vocab/format-assertion',
        'https://json-schema.org/draft/2020-12/vocab/content',
        'https://spec.openapis.org/oas/3.1/vocab/base',
    ];

    public function __construct(
        private readonly SchemaSubschemaWalkerInterface $walker = new SchemaSubschemaWalker(),
        private readonly LegacyKeywordDetector $legacyKeywordDetector = new LegacyKeywordDetector(),
        private readonly DocumentSchemaCollector $documentSchemaCollector = new DocumentSchemaCollector(),
    ) {}

    public function guard(OpenApiDocument $document, ?EventDispatcherInterface $eventDispatcher = null): void
    {
        $disabledVocabularyUris = [];
        $legacyKeywords = [];

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();

        foreach ($this->documentSchemaCollector->collect($document) as $schema) {
            $scan = $this->scanSchema($schema, $visited);
            $disabledVocabularyUris += $scan['vocabulary'];
            $legacyKeywords += $scan['legacy'];
        }

        $this->dispatchWarnings($disabledVocabularyUris, $legacyKeywords, $eventDispatcher);
    }

    /**
     * @param WeakMap<Schema, true> $visited
     * @return array{vocabulary: array<string, true>, legacy: array<string, string>}
     */
    private function scanSchema(Schema $schema, WeakMap $visited): array
    {
        if (isset($visited[$schema])) {
            return ['vocabulary' => [], 'legacy' => []];
        }

        $visited[$schema] = true;

        $scan = [
            'vocabulary' => $this->unsupportedVocabularyUris($schema),
            'legacy' => $this->legacyKeywordDetector->detect($schema),
        ];

        foreach ($this->walker->iterateSubSchemas($schema) as $subSchema) {
            $subScan = $this->scanSchema($subSchema, $visited);
            $scan['vocabulary'] += $subScan['vocabulary'];
            $scan['legacy'] += $subScan['legacy'];
        }

        return $scan;
    }

    /** @return array<string, true> */
    private function unsupportedVocabularyUris(Schema $schema): array
    {
        $unsupported = [];

        foreach ($schema->vocabulary ?? [] as $uri => $required) {
            if (in_array($uri, self::SUPPORTED_VOCABULARY_URIS, true)) {
                continue;
            }

            if ($required) {
                throw new VocabularyNotSupportedException(vocabularyUri: $uri);
            }

            $unsupported[$uri] = true;
        }

        return $unsupported;
    }

    /**
     * @param array<string, true> $disabledVocabularyUris
     * @param array<string, string> $legacyKeywords
     */
    private function dispatchWarnings(
        array $disabledVocabularyUris,
        array $legacyKeywords,
        ?EventDispatcherInterface $eventDispatcher,
    ): void {
        foreach (array_keys($disabledVocabularyUris) as $uri) {
            $eventDispatcher?->dispatch(new VocabularyDisabledWarningEvent($uri));
        }

        foreach ($legacyKeywords as $keyword => $migrationTarget) {
            $eventDispatcher?->dispatch(new LegacyKeywordWarningEvent($keyword, $migrationTarget));
        }
    }
}
