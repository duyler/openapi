<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaSiblingMerger;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\DocumentRepository;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\RefCache;
use WeakMap;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function preg_match;
use function rawurldecode;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;

/** @internal */
final class DocumentNavigator
{
    private const int REF_ROOT_PREFIX_LENGTH = 2;

    private const string NCNAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_.\-]*$/';

    private const string REASON_PROPERTY_MISSING = 'Property does not exist';

    private const string REASON_ARRAY_KEY_MISSING = 'Array key does not exist';

    private const string REASON_VALUE_NULL = 'Value is null';

    private const string REASON_VALUE_NOT_CONTAINER = 'Value is not an object or array';

    private const string REASON_NOT_NCNAME = 'Plain-name fragment is not a valid NCName';

    private const string REASON_EMPTY_FRAGMENT = 'Ref has an empty fragment';

    private const string REASON_ANCHOR_UNRESOLVED = 'Fragment does not resolve to an anchor in the document';

    private const string REASON_REGISTERED_ROOT_NOT_A_SCHEMA
        = 'Registered document root is not a schema object; reference an $id or a fragment of the document';

    private const string REASON_FILE_FALLBACK_DENIED
        = 'External ref is not registered in the document repository; file fallback is denied because '
        . 'externalRefAllowedRoot is not set. Call withExternalRefAllowedRoot($path), register the referenced '
        . 'document via withDocument(), or inject a custom ExternalRefResolverInterface.';

    private ?ChainResources $chainResources = null;

    private ?SchemaFromArrayConverter $rawSchemaConverter = null;

    private readonly DialectProfile $dialectProfile;

    /**
     * @param Closure(OpenApiDocument): ResourceRegistry|null $registryProvider
     */
    public function __construct(
        private readonly FileExternalRefResolver $builtinFileResolver,
        private readonly ?ExternalRefResolverInterface $externalRefResolver = null,
        private readonly ?Closure $registryProvider = null,
        private readonly UriResolver $uriResolver = new UriResolver(),
        private readonly ?DocumentRepository $documentRepository = null,
        ?DialectProfile $dialectProfile = null,
    ) {
        $this->dialectProfile = $dialectProfile ?? DialectProfile::forOpenApi31();
    }

    public function collectChainResources(ChainResources $chainResources): void
    {
        $this->chainResources = $chainResources;
    }

    public function stopChainResourcesCollection(): void
    {
        $this->chainResources = null;
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    public function resolveRef(
        string $ref,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth = 0,
        ?string $baseUri = null,
        bool $followChain = true,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ('#' === $ref) {
            return $this->resolveRootRef($ref, $document, $visited, $cache, $depth, $baseUri, $followChain);
        }

        if (str_starts_with($ref, '#/')) {
            return $this->resolvePointerRef($ref, $document, $visited, $cache, $depth, $baseUri, $followChain);
        }

        if (str_starts_with($ref, '#')) {
            return $this->resolvePlainNameRef($ref, substr($ref, 1), $document, $visited, $cache, $depth, $baseUri, $followChain);
        }

        [$resourceUri, $fragment] = $this->splitUriRef($ref);

        $viaRegistry = null === $this->registryProvider
            ? null
            : $this->resolveUriRefViaRegistry($ref, $resourceUri, $fragment, $document, $visited, $cache, $depth, $baseUri, $followChain);

        if (null !== $viaRegistry) {
            return $viaRegistry;
        }

        return $this->resolveExternalRef($ref, $document, $baseUri, $visited, $cache, $depth, $followChain);
    }

    /**
     * @param array<int, string> $parts
     */
    public function navigate(
        object|array $current,
        array $parts,
        int $depth = 0,
        int $maxDepth = ValidationContext::MAX_DEPTH,
    ): Schema|Parameter|RequestBody|Response {
        $count = count($parts);

        for ($i = 0; $i < $count; ++$i) {
            if ($depth + $i >= $maxDepth) {
                throw new SchemaDepthExceededException($maxDepth);
            }

            $decodedSegment = $this->decodePointerSegment($parts[$i]);
            $current = $this->getProperty($current, $decodedSegment);
        }

        if (
            $current instanceof Schema
            || $current instanceof Parameter
            || $current instanceof RequestBody
            || $current instanceof Response
        ) {
            return $current;
        }

        throw new UnresolvableRefException(
            '',
            'Target is not a Schema, Parameter, RequestBody, or Response',
        );
    }

    public function getProperty(
        object|array $container,
        string $property,
    ): object|array {
        $value = match (true) {
            is_array($container) => array_key_exists($property, $container)
                ? $container[$property]
                : throw new UnresolvableRefException($property, self::REASON_ARRAY_KEY_MISSING),
            property_exists($container, $property) => $container->$property,
            default => throw new UnresolvableRefException($property, self::REASON_PROPERTY_MISSING),
        };

        if (null === $value) {
            throw new UnresolvableRefException($property, self::REASON_VALUE_NULL);
        }

        if (false === is_object($value) && false === is_array($value)) {
            throw new UnresolvableRefException(
                $property,
                self::REASON_VALUE_NOT_CONTAINER,
            );
        }

        return $value;
    }

    /** @param array<string, bool> $visited */
    public function formatCircularPath(array $visited, string $circularRef): string
    {
        $path = array_keys($visited);
        $path[] = $circularRef;

        return implode(' -> ', $path);
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function resolvePointerRef(
        string $ref,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth,
        ?string $baseUri,
        bool $followChain = true,
    ): array {
        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $result = str_starts_with($ref, '#/$defs')
            ? $this->resolvePointerViaRegistry($ref, $document, $baseUri)
                ?? $this->navigateThrowing($ref, $document, explode('/', substr($ref, self::REF_ROOT_PREFIX_LENGTH)))
            : $this->resolvePointerViaDocumentOrResource($ref, $document, $baseUri);

        return $this->finishResolve($result, $ref, $document, $visited, $cache, $depth, $baseUri, $followChain);
    }

    private function resolvePointerViaDocumentOrResource(
        string $ref,
        OpenApiDocument $document,
        ?string $baseUri,
    ): Schema|Parameter|RequestBody|Response {
        try {
            return $this->navigateThrowing($ref, $document, explode('/', substr($ref, self::REF_ROOT_PREFIX_LENGTH)));
        } catch (UnresolvableRefException $exception) {
            if (false === $this->isMissingPointerReason($exception->reason())) {
                throw $exception;
            }

            return $this->resolvePointerViaRegistry($ref, $document, $baseUri)
                ?? throw $exception;
        }
    }

    private function isMissingPointerReason(string $reason): bool
    {
        return self::REASON_PROPERTY_MISSING === $reason
            || self::REASON_ARRAY_KEY_MISSING === $reason;
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function resolvePlainNameRef(
        string $ref,
        string $name,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth,
        ?string $baseUri,
        bool $followChain = true,
    ): array {
        if ('' === $name) {
            throw new UnresolvableRefException($ref, self::REASON_EMPTY_FRAGMENT);
        }

        $this->assertPlainNameFragment($name, $ref);

        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $result = $this->findAnchoredSchema($name, $this->registry($document));

        if (null === $result) {
            throw new UnresolvableRefException($ref, self::REASON_ANCHOR_UNRESOLVED);
        }

        return $this->finishResolve($result, $ref, $document, $visited, $cache, $depth, $baseUri, $followChain);
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}|null
     */
    private function resolveUriRefViaRegistry(
        string $ref,
        string $resourceUri,
        ?string $fragment,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth,
        ?string $baseUri,
        bool $followChain = true,
    ): ?array {
        $registry = $this->registry($document);

        if (null === $registry) {
            return null;
        }

        $resource = $this->findResource($registry, $resourceUri, $document, $baseUri);

        if (null === $resource) {
            return null;
        }

        [$resourceSchema, $absoluteResourceUri] = $resource;

        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $result = $this->resolveFragmentInResource($ref, $absoluteResourceUri, $fragment, $resourceSchema, $registry);

        if (null === $result) {
            throw new UnresolvableRefException($ref, self::REASON_ANCHOR_UNRESOLVED);
        }

        $this->collectEnclosingResource($resourceSchema, $result);

        return $this->finishResolve($result, $ref, $document, $visited, $cache, $depth, $baseUri, $followChain, $absoluteResourceUri);
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function finishResolve(
        Schema|Parameter|RequestBody|Response $result,
        string $ref,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth,
        ?string $baseUri,
        bool $followChain,
        ?string $resourceUri = null,
    ): array {
        if ($followChain && null !== $result->ref) {
            $this->collectChainResource($result);

            [$resolved, $visited] = $this->resolveRef(
                $result->ref,
                $document,
                $visited,
                $cache,
                $depth + 1,
                $resourceUri ?? $result->id ?? $baseUri,
                $followChain,
            );

            return [$this->mergeChainSiblings($resolved, $result), $visited];
        }

        if ($followChain) {
            $this->storeCached($document, $ref, $result, $cache);
        }

        return [$result, $visited];
    }

    private function collectChainResource(Schema|Parameter|RequestBody|Response|null $resource): void
    {
        if (null === $this->chainResources) {
            return;
        }

        if (null === $resource) {
            return;
        }

        if (false === $resource instanceof Schema || null === $resource->id) {
            return;
        }

        $this->chainResources->add($resource);
    }

    /**
     * @param Schema|Parameter|RequestBody|Response $resolved
     * @return Schema|Parameter|RequestBody|Response
     */
    private function mergeChainSiblings(
        Schema|Parameter|RequestBody|Response $resolved,
        Schema|Parameter|RequestBody|Response $carrier,
    ): Schema|Parameter|RequestBody|Response {
        if (false === $this->dialectProfile->refSiblingsApply) {
            return $resolved;
        }

        if (false === $resolved instanceof Schema || false === $carrier instanceof Schema) {
            return $resolved;
        }

        return new SchemaSiblingMerger()->merge($resolved, $carrier);
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function resolveRootRef(
        string $ref,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth,
        ?string $baseUri,
        bool $followChain,
    ): array {
        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $registry = $this->registry($document);

        if (null === $registry) {
            throw new UnresolvableRefException($ref, self::REASON_ANCHOR_UNRESOLVED);
        }

        $fallbackBase = $baseUri ?? $document->self;
        $resource = null;

        foreach ($this->scopeRegistries($document) as $scopeRegistry) {
            $resource = null === $fallbackBase ? null : $scopeRegistry->findByUri($fallbackBase);

            if (null !== $resource) {
                break;
            }
        }

        $resource ??= $registry->roots()[0] ?? null;

        if (null === $resource) {
            throw new UnresolvableRefException($ref, self::REASON_ANCHOR_UNRESOLVED);
        }

        return $this->finishResolve($resource, $ref, $document, $visited, $cache, $depth, $baseUri, $followChain);
    }

    /** @return list<ResourceRegistry> */
    private function scopeRegistries(OpenApiDocument $document): array
    {
        $registries = [];

        foreach ([$document, ...($this->documentRepository?->documents() ?? [])] as $candidate) {
            $registry = $this->registry($candidate);

            if ($registry instanceof ResourceRegistry) {
                $registries[] = $registry;
            }
        }

        return $registries;
    }

    private function resolvePointerViaRegistry(
        string $ref,
        OpenApiDocument $document,
        ?string $baseUri,
    ): Schema|Parameter|RequestBody|Response|null {
        $segments = array_map(
            $this->decodePointerSegment(...),
            explode('/', substr($ref, 1)),
        );

        if ('' !== ($segments[0] ?? null)) {
            return null;
        }

        foreach ($this->pointerRegistries($document, $baseUri) as $registry) {
            foreach ($this->pointerRoots($registry, $baseUri) as $root) {
                $result = $this->navigateFromSchema($root, $segments, 1);

                if (null !== $result) {
                    return $result;
                }
            }
        }

        return null;
    }

    /** @return list<ResourceRegistry> */
    private function pointerRegistries(OpenApiDocument $document, ?string $baseUri): array
    {
        $registries = [];
        $primary = $this->registry($document);

        if (null !== $primary) {
            $registries[] = $primary;
        }

        if (null === $baseUri) {
            return $registries;
        }

        foreach ($this->documentRepository?->documents() ?? [] as $candidate) {
            $candidateRegistry = $this->registry($candidate);

            if (null !== $candidateRegistry && null !== $candidateRegistry->findByUri($baseUri)) {
                $registries[] = $candidateRegistry;
            }
        }

        return $registries;
    }

    private function resolveFragmentInResource(
        string $ref,
        string $resourceUri,
        ?string $fragment,
        Schema $resource,
        ResourceRegistry $registry,
    ): Schema|Parameter|RequestBody|Response|null {
        if (null === $fragment || '' === $fragment) {
            return $resource;
        }

        if (str_starts_with($fragment, '/')) {
            $segments = array_map(
                $this->decodePointerSegment(...),
                explode('/', substr($fragment, 1)),
            );

            return $this->navigateFromSchema($resource, $segments, 0);
        }

        $this->assertPlainNameFragment($fragment, $ref);

        return $registry->findAnchorIn($resourceUri, $fragment)
            ?? $registry->findDynamicAnchorIn($resourceUri, $fragment);
    }

    /**
     * @param list<string> $segments
     */
    private function navigateFromSchema(
        Schema $root,
        array $segments,
        int $offset,
    ): Schema|Parameter|RequestBody|Response|null {
        $current = $root;
        $count = count($segments);

        for ($i = $offset; $i < $count; ++$i) {
            if (is_bool($current)) {
                break;
            }

            $segment = '$defs' === $segments[$i] ? 'defs' : $segments[$i];

            try {
                $current = $this->getProperty($current, $segment);
            } catch (UnresolvableRefException) {
                $extrasValue = $current instanceof Schema
                    ? $current->extras[$segment] ?? null
                    : null;

                if (false === is_array($extrasValue) && false === is_bool($extrasValue)) {
                    return null;
                }

                $current = $extrasValue;
            }
        }

        if (
            $current instanceof Schema
            || $current instanceof Parameter
            || $current instanceof RequestBody
            || $current instanceof Response
        ) {
            return $current;
        }

        if (is_bool($current)) {
            return $this->rawSchemaConverter()->fromArray($current);
        }

        if (is_array($current) && [] !== $current && false === $this->isSchemaCollection($current)) {
            return $this->rawSchemaConverter()->fromArray($current);
        }

        return null;
    }

    /** @param array<array-key, mixed> $current */
    private function isSchemaCollection(array $current): bool
    {
        $schemaValues = array_filter(
            $current,
            static fn(mixed $value): bool => $value instanceof Schema || is_bool($value),
        );

        return [] !== $schemaValues;
    }

    private function rawSchemaConverter(): SchemaFromArrayConverter
    {
        return $this->rawSchemaConverter ??= new SchemaFromArrayConverter(
            dialectProfile: $this->dialectProfile,
        );
    }

    private function findAnchoredSchema(string $name, ?ResourceRegistry $registry): ?Schema
    {
        if (null === $registry) {
            return null;
        }

        return $registry->findByAnchor($name) ?? $registry->findDynamicAnchor($name);
    }

    /**
     * @return array{Schema, string}|null
     */
    private function findResource(
        ResourceRegistry $registry,
        string $resourceUri,
        OpenApiDocument $document,
        ?string $baseUri,
    ): ?array {
        if ('' === $resourceUri) {
            $fallbackBase = $baseUri ?? $document->self;

            if (null === $fallbackBase) {
                return null;
            }

            $root = $registry->findByUri($fallbackBase);

            return null === $root ? null : [$root, $fallbackBase];
        }

        $direct = $registry->findByUri($resourceUri);
        if (null !== $direct) {
            return [$direct, $resourceUri];
        }

        foreach ([$baseUri, $document->self] as $candidate) {
            if (null === $candidate || '' === $candidate) {
                continue;
            }

            $absoluteUri = $this->uriResolver->combine($candidate, $resourceUri);
            $combined = $registry->findByUri($absoluteUri);

            if (null !== $combined) {
                return [$combined, $absoluteUri];
            }
        }

        return null;
    }

    /** @return list<Schema> */
    private function pointerRoots(ResourceRegistry $registry, ?string $baseUri): array
    {
        if (null === $baseUri) {
            return $registry->roots();
        }

        $preferred = $registry->findByUri($baseUri);

        if (null === $preferred) {
            return $registry->roots();
        }

        $roots = [$preferred];

        foreach ($registry->roots() as $root) {
            if ($root !== $preferred) {
                $roots[] = $root;
            }
        }

        return $roots;
    }

    private function registry(OpenApiDocument $document): ?ResourceRegistry
    {
        if (null === $this->registryProvider) {
            return null;
        }

        return ($this->registryProvider)($document);
    }

    /** @return array{string, ?string} */
    private function splitUriRef(string $ref): array
    {
        $position = strpos($ref, '#');

        if (false === $position) {
            return [$ref, null];
        }

        return [substr($ref, 0, $position), substr($ref, $position + 1)];
    }

    private function assertPlainNameFragment(string $name, string $ref): void
    {
        if (1 === preg_match(self::NCNAME_PATTERN, $name)) {
            return;
        }

        throw new UnresolvableRefException($ref, self::REASON_NOT_NCNAME);
    }

    /** @param array<string, bool> $visited */
    private function assertNotCircular(string $ref, array $visited): void
    {
        if (isset($visited[$ref])) {
            throw new UnresolvableRefException(
                $ref,
                'Circular reference detected',
                internalTrace: $this->formatCircularPath($visited, $ref),
            );
        }
    }

    /** @param array<int, string> $parts */
    private function navigateThrowing(string $ref, OpenApiDocument $document, array $parts): Schema|Parameter|RequestBody|Response
    {
        try {
            return $this->navigate($document, $parts);
        } catch (UnresolvableRefException $e) {
            throw new UnresolvableRefException($ref, $e->reason(), previous: $e);
        }
    }

    /** @param WeakMap<OpenApiDocument, RefCache> $cache */
    private function lookupCached(OpenApiDocument $document, string $ref, WeakMap $cache): Schema|Parameter|RequestBody|Response|null
    {
        if (false === isset($cache[$document])) {
            return null;
        }

        /** @var RefCache $existing */
        $existing = $cache[$document];

        return $existing->map[$ref] ?? null;
    }

    /** @param WeakMap<OpenApiDocument, RefCache> $cache */
    private function storeCached(OpenApiDocument $document, string $ref, Schema|Parameter|RequestBody|Response $result, WeakMap $cache): void
    {
        /** @var RefCache $refCache */
        $refCache = $cache[$document] ?? new RefCache();
        $cache[$document] = $refCache;
        $refCache->map[$ref] = $result;
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function resolveExternalRef(
        string $ref,
        OpenApiDocument $document,
        ?string $baseUri,
        array $visited,
        WeakMap $cache,
        int $depth,
        bool $followChain = true,
    ): array {
        $viaRepository = $this->resolveViaRepository($ref, $document, $baseUri, $visited, $cache, $depth, $followChain);

        if (null !== $viaRepository) {
            return $viaRepository;
        }

        if ($this->isFileFallbackDenied()) {
            throw new UnresolvableRefException($ref, self::REASON_FILE_FALLBACK_DENIED);
        }

        try {
            $resolver = $this->externalRefResolver ?? $this->builtinFileResolver;

            return [$resolver->resolve($ref), $visited];
        } catch (ExternalRefSecurityException $e) {
            throw new UnresolvableRefException(
                $ref,
                'External ref not resolved. Builtin FileExternalRefResolver allows only '
                . 'file:// URIs and scheme-less relative paths; every other scheme '
                . '(http, https, ftp, php, phar, data, compress.zlib, zip, expect, '
                . 'ssh2, rar, ogg, glob, etc.) is rejected. Inject a custom '
                . 'ExternalRefResolverInterface implementation to enable other schemes.',
                previous: $e,
            );
        }
    }

    private function isFileFallbackDenied(): bool
    {
        return null !== $this->documentRepository
            && [] !== $this->documentRepository->contents()
            && null === $this->externalRefResolver
            && null === $this->builtinFileResolver->allowedRoot();
    }

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}|null
     */
    private function resolveViaRepository(
        string $ref,
        OpenApiDocument $document,
        ?string $baseUri,
        array $visited,
        WeakMap $cache,
        int $depth,
        bool $followChain = true,
    ): ?array {
        $repository = $this->documentRepository;

        if (null === $repository) {
            return null;
        }

        [$resourceUri, $fragment] = $this->splitUriRef($ref);

        $match = $this->matchRetrievalUri($resourceUri, $repository, $document, $baseUri);

        if (null === $match) {
            return null;
        }

        [$targetDocument, $retrievalUri] = $match;

        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($targetDocument, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $result = $this->resolveInRegisteredDocument($ref, $targetDocument, $retrievalUri, $fragment);

        if ($followChain && null !== $result->ref) {
            $this->collectChainResource($result);

            [$resolved, $visited] = $this->resolveRef(
                $result->ref,
                $targetDocument,
                $visited,
                $cache,
                $depth + 1,
                $result->id ?? $retrievalUri,
                $followChain,
            );

            return [$this->mergeChainSiblings($resolved, $result), $visited];
        }

        if ($followChain) {
            $this->storeCached($targetDocument, $ref, $result, $cache);
        }

        return [$result, $visited];
    }

    /**
     * @return array{OpenApiDocument, string}|null
     */
    private function matchRetrievalUri(
        string $resourceUri,
        DocumentRepository $repository,
        OpenApiDocument $document,
        ?string $baseUri,
    ): ?array {
        $exact = $repository->findByUri($resourceUri);

        if (null !== $exact) {
            return [$exact, $resourceUri];
        }

        foreach ([$baseUri, $document->self] as $candidate) {
            if (null === $candidate || '' === $candidate) {
                continue;
            }

            $absoluteUri = $this->uriResolver->combine($candidate, $resourceUri);
            $combined = $repository->findByUri($absoluteUri);

            if (null !== $combined) {
                return [$combined, $absoluteUri];
            }
        }

        foreach (array_keys($repository->contents()) as $registeredUri) {
            $absoluteUri = $this->uriResolver->combine($registeredUri, $resourceUri);
            $combined = $repository->findByUri($absoluteUri);

            if (null !== $combined) {
                return [$combined, $absoluteUri];
            }
        }

        return null;
    }

    private function resolveInRegisteredDocument(
        string $ref,
        OpenApiDocument $targetDocument,
        string $retrievalUri,
        ?string $fragment,
    ): Schema|Parameter|RequestBody|Response {
        $registry = $this->registry($targetDocument);

        if (null === $fragment || '' === $fragment) {
            $root = $registry?->findByUri($retrievalUri);
            $fallbackRoot = $registry?->roots()[0] ?? null;

            $root ??= null !== $fallbackRoot && null !== $fallbackRoot->id ? $fallbackRoot : null;

            if (null === $root) {
                throw new UnresolvableRefException($ref, self::REASON_REGISTERED_ROOT_NOT_A_SCHEMA);
            }

            $this->collectChainResource($root);

            return $root;
        }

        if (str_starts_with($fragment, '/')) {
            $segments = array_map($this->decodePointerSegment(...), explode('/', substr($fragment, 1)));

            $root = $registry?->findByUri($retrievalUri);

            if (null !== $root) {
                $fromRoot = $this->navigateFromSchema($root, $segments, 0);

                if (null !== $fromRoot) {
                    $this->collectEnclosingResource($root, $fromRoot);

                    return $fromRoot;
                }
            }

            try {
                return $this->navigate($targetDocument, $segments);
            } catch (UnresolvableRefException $e) {
                throw new UnresolvableRefException($ref, $e->reason(), previous: $e);
            }
        }

        $this->assertPlainNameFragment($fragment, $ref);

        $anchored = $registry?->findAnchorIn($retrievalUri, $fragment)
            ?? $registry?->findByAnchor($fragment)
            ?? $registry?->findDynamicAnchorIn($retrievalUri, $fragment)
            ?? $registry?->findDynamicAnchor($fragment)
            ?? throw new UnresolvableRefException($ref, self::REASON_ANCHOR_UNRESOLVED);

        $this->collectEnclosingResource($registry?->findByUri($retrievalUri), $anchored);

        return $anchored;
    }

    private function collectEnclosingResource(
        Schema|Parameter|RequestBody|Response|null $resource,
        Schema|Parameter|RequestBody|Response $target,
    ): void {
        if (false === $target instanceof Schema || null === $target->id) {
            $this->collectChainResource($resource);
        }
    }

    private function decodePointerSegment(string $segment): string
    {
        $decoded = rawurldecode($segment);

        return str_replace(['~1', '~0'], ['/', '~'], $decoded);
    }
}
