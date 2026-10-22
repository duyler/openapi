<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
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
use function is_object;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;

/** @internal */
final readonly class DocumentNavigator
{
    private const int REF_ROOT_PREFIX_LENGTH = 2;

    private const string NCNAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_.\-]*$/';

    private const string REASON_NOT_NCNAME = 'Plain-name fragment is not a valid NCName';

    private const string REASON_EMPTY_FRAGMENT = 'Ref has an empty fragment';

    private const string REASON_ANCHOR_UNRESOLVED = 'Fragment does not resolve to an anchor in the document';

    /**
     * @param Closure(OpenApiDocument): ResourceRegistry|null $registryProvider
     */
    public function __construct(
        private readonly FileExternalRefResolver $builtinFileResolver,
        private readonly ?ExternalRefResolverInterface $externalRefResolver = null,
        private readonly ?Closure $registryProvider = null,
        private readonly UriResolver $uriResolver = new UriResolver(),
    ) {}

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
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if (str_starts_with($ref, '#/')) {
            return $this->resolvePointerRef($ref, $document, $visited, $cache, $depth, $baseUri);
        }

        if (str_starts_with($ref, '#')) {
            return $this->resolvePlainNameRef($ref, substr($ref, 1), $document, $visited, $cache, $depth, $baseUri);
        }

        [$resourceUri, $fragment] = $this->splitUriRef($ref);

        $viaRegistry = null === $this->registryProvider
            ? null
            : $this->resolveUriRefViaRegistry($ref, $resourceUri, $fragment, $document, $visited, $cache, $depth, $baseUri);

        if (null !== $viaRegistry) {
            return $viaRegistry;
        }

        return $this->resolveExternalRef($ref, $visited);
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
                : throw new UnresolvableRefException($property, 'Array key does not exist'),
            property_exists($container, $property) => $container->$property,
            default => throw new UnresolvableRefException($property, 'Property does not exist'),
        };

        if (null === $value) {
            throw new UnresolvableRefException($property, 'Value is null');
        }

        if (false === is_object($value) && false === is_array($value)) {
            throw new UnresolvableRefException(
                $property,
                'Value is not an object or array',
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
    ): array {
        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $result = $this->resolvePointerViaRegistry($ref, $document, $baseUri)
            ?? $this->navigateThrowing($ref, $document, explode('/', substr($ref, self::REF_ROOT_PREFIX_LENGTH)));

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited, $cache, $depth + 1, $result->id ?? $baseUri);
        }

        $this->storeCached($document, $ref, $result, $cache);

        return [$result, $visited];
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

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited, $cache, $depth + 1, $result->id ?? $baseUri);
        }

        $this->storeCached($document, $ref, $result, $cache);

        return [$result, $visited];
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

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited, $cache, $depth + 1, $result->id ?? $baseUri);
        }

        $this->storeCached($document, $ref, $result, $cache);

        return [$result, $visited];
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

        if ('$defs' !== ($segments[1] ?? null)) {
            return null;
        }

        $registry = $this->registry($document);

        if (null === $registry) {
            return null;
        }

        foreach ($this->pointerRoots($registry, $baseUri) as $root) {
            $result = $this->navigateFromSchema($root, $segments, 1);

            if (null !== $result) {
                return $result;
            }
        }

        return null;
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
            $segment = '$defs' === $segments[$i] ? 'defs' : $segments[$i];

            try {
                $current = $this->getProperty($current, $segment);
            } catch (UnresolvableRefException) {
                return null;
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

        return null;
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
     * @return array{Schema|Parameter|RequestBody|Response, array<string, bool>}
     */
    private function resolveExternalRef(string $ref, array $visited): array
    {
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

    private function decodePointerSegment(string $segment): string
    {
        return str_replace(['~1', '~0'], ['/', '~'], $segment);
    }
}
