<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Event\LegacyKeywordWarningEvent;
use Duyler\OpenApi\Event\VocabularyDisabledWarningEvent;
use Duyler\OpenApi\Schema\Exception\VocabularyNotSupportedException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalkerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use WeakMap;

use function array_filter;
use function array_keys;
use function array_values;
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
    ) {}

    public function guard(OpenApiDocument $document, ?EventDispatcherInterface $eventDispatcher = null): void
    {
        $disabledVocabularyUris = [];
        $legacyKeywords = [];

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();

        foreach ($this->documentSchemas($document) as $schema) {
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

    /** @return list<Schema> */
    private function documentSchemas(OpenApiDocument $document): array
    {
        $schemas = [];

        foreach ($document->paths?->paths ?? [] as $pathItem) {
            $schemas = $this->withPathItem($pathItem, $schemas);
        }

        foreach ($document->webhooks?->webhooks ?? [] as $pathItem) {
            $schemas = $this->withPathItem($pathItem, $schemas);
        }

        $components = $document->components;

        if (null === $components) {
            return $schemas;
        }

        foreach ($components->schemas ?? [] as $schema) {
            $schemas[] = $schema;
        }

        foreach ($components->responses ?? [] as $response) {
            $schemas = $this->withResponse($response, $schemas);
        }

        foreach ($components->parameters ?? [] as $parameter) {
            $schemas = $this->withParameter($parameter, $schemas);
        }

        foreach ($components->requestBodies ?? [] as $requestBody) {
            $schemas = $this->withContent($requestBody->content, $schemas);
        }

        foreach ($components->headers ?? [] as $header) {
            $schemas = $this->withHeader($header, $schemas);
        }

        foreach ($components->links ?? [] as $link) {
            $schemas = $this->withLink($link, $schemas);
        }

        foreach ($components->callbacks ?? [] as $callbacks) {
            $schemas = $this->withCallbacks($callbacks, $schemas);
        }

        foreach ($components->pathItems ?? [] as $pathItem) {
            $schemas = $this->withPathItem($pathItem, $schemas);
        }

        foreach ($components->mediaTypes ?? [] as $mediaType) {
            $schemas = $this->withMediaType($mediaType, $schemas);
        }

        return $schemas;
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withPathItem(PathItem $pathItem, array $schemas): array
    {
        foreach ($this->operations($pathItem) as $operation) {
            $schemas = $this->withOperation($operation, $schemas);
        }

        foreach ($pathItem->parameters?->parameters ?? [] as $parameter) {
            if ($parameter instanceof Parameter) {
                $schemas = $this->withParameter($parameter, $schemas);
            }
        }

        return $schemas;
    }

    /** @return list<Operation> */
    private function operations(PathItem $pathItem): array
    {
        /** @var list<Operation|null> $candidates */
        $candidates = [
            $pathItem->get,
            $pathItem->put,
            $pathItem->post,
            $pathItem->delete,
            $pathItem->options,
            $pathItem->head,
            $pathItem->patch,
            $pathItem->trace,
            $pathItem->query,
        ];

        foreach ($pathItem->additionalOperations ?? [] as $additionalOperation) {
            $candidates[] = $additionalOperation;
        }

        return array_values(array_filter(
            $candidates,
            static fn(?Operation $operation): bool => null !== $operation,
        ));
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withOperation(Operation $operation, array $schemas): array
    {
        foreach ($operation->parameters?->parameters ?? [] as $parameter) {
            if ($parameter instanceof Parameter) {
                $schemas = $this->withParameter($parameter, $schemas);
            }
        }

        if (null !== $operation->requestBody) {
            $schemas = $this->withContent($operation->requestBody->content, $schemas);
        }

        foreach ($operation->responses?->responses ?? [] as $response) {
            $schemas = $this->withResponse($response, $schemas);
        }

        if (null !== $operation->callbacks) {
            $schemas = $this->withCallbacks($operation->callbacks, $schemas);
        }

        return $schemas;
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withCallbacks(Callbacks $callbacks, array $schemas): array
    {
        foreach ($callbacks->callbacks as $callback) {
            foreach ($callback as $pathItem) {
                $schemas = $this->withPathItem($pathItem, $schemas);
            }
        }

        return $schemas;
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withResponse(Response $response, array $schemas): array
    {
        foreach ($response->headers?->headers ?? [] as $header) {
            $schemas = $this->withHeader($header, $schemas);
        }

        $schemas = $this->withContent($response->content, $schemas);

        foreach ($response->links?->links ?? [] as $link) {
            $schemas = $this->withLink($link, $schemas);
        }

        return $schemas;
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withParameter(Parameter $parameter, array $schemas): array
    {
        if (null !== $parameter->schema) {
            $schemas[] = $parameter->schema;
        }

        return $this->withContent($parameter->content, $schemas);
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withHeader(Header $header, array $schemas): array
    {
        if (null !== $header->schema) {
            $schemas[] = $header->schema;
        }

        return $this->withContent($header->content, $schemas);
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withLink(Link $link, array $schemas): array
    {
        if (null === $link->requestBody) {
            return $schemas;
        }

        return $this->withContent($link->requestBody->content, $schemas);
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withContent(?Content $content, array $schemas): array
    {
        foreach ($content?->mediaTypes ?? [] as $mediaType) {
            $schemas = $this->withMediaType($mediaType, $schemas);
        }

        return $schemas;
    }

    /**
     * @param list<Schema> $schemas
     * @return list<Schema>
     */
    private function withMediaType(MediaType $mediaType, array $schemas): array
    {
        if (null !== $mediaType->schema) {
            $schemas[] = $mediaType->schema;
        }

        if (null !== $mediaType->itemSchema) {
            $schemas[] = $mediaType->itemSchema;
        }

        return $schemas;
    }
}
