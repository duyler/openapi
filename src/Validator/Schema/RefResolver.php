<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Override;
use WeakMap;

use Generator;

use function array_key_exists;
use function is_array;
use function is_object;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function count;
use function is_int;
use function is_string;

final class RefResolver implements RefResolverInterface
{
    private const int REF_ROOT_PREFIX_LENGTH = 2;

    /** @var WeakMap<OpenApiDocument, RefCache> */
    private WeakMap $cache;

    /** @var WeakMap<Schema, WeakMap<OpenApiDocument, bool>> */
    private WeakMap $hasDiscriminatorCache;

    /** @var WeakMap<Schema, bool> */
    private WeakMap $hasRefCache;

    private readonly FileExternalRefResolver $builtinFileResolver;

    public function __construct(
        private readonly ?ExternalRefResolverInterface $externalRefResolver = null,
        ?FileExternalRefResolver $builtinFileResolver = null,
    ) {
        $this->clear();
        $this->builtinFileResolver = $builtinFileResolver ?? new FileExternalRefResolver();
    }

    #[Override]
    public function clear(): void
    {
        /** @var WeakMap<OpenApiDocument, RefCache> $cache */
        $cache = new WeakMap();
        $this->cache = $cache;
        /** @var WeakMap<Schema, WeakMap<OpenApiDocument, bool>> $hasDiscriminatorCache */
        $hasDiscriminatorCache = new WeakMap();
        $this->hasDiscriminatorCache = $hasDiscriminatorCache;
        /** @var WeakMap<Schema, bool> $hasRefCache */
        $hasRefCache = new WeakMap();
        $this->hasRefCache = $hasRefCache;
    }

    #[Override]
    public function getBaseUri(OpenApiDocument $document): ?string
    {
        return $document->self;
    }

    #[Override]
    public function resolveRelativeRef(
        string $ref,
        OpenApiDocument $document,
    ): string {
        $baseUri = $this->getBaseUri($document);

        if (null === $baseUri) {
            throw new RefResolutionException(
                "Cannot resolve relative reference '{$ref}' without document \$self or base URI",
            );
        }

        return $this->combineUris($baseUri, $ref);
    }

    #[Override]
    public function combineUris(string $baseUri, string $relativeRef): string
    {
        if ('' === $relativeRef) {
            return $baseUri;
        }

        $relative = parse_url($relativeRef);
        if (false === $relative) {
            return $relativeRef;
        }

        if (isset($relative['scheme'])) {
            return $relativeRef;
        }

        $base = parse_url($baseUri);
        if (false === $base) {
            return $baseUri;
        }

        return $this->resolveRelativeAgainstBase($base, $relative, $baseUri);
    }

    /**
     * @throws SchemaDepthExceededException
     */
    #[Override]
    public function resolve(string $ref, OpenApiDocument $document, int $depth = 0): Schema
    {
        /** @var array<string, bool> $visited */
        $visited = [];
        [$result,] = $this->resolveRef($ref, $document, $visited, $depth);

        if (false === $result instanceof Schema) {
            throw new UnresolvableRefException(
                $ref,
                "Expected Schema but got " . $result::class,
            );
        }

        return $result;
    }

    /**
     * @throws SchemaDepthExceededException
     */
    #[Override]
    public function resolveParameter(
        string $ref,
        OpenApiDocument $document,
        int $depth = 0,
    ): Parameter {
        /** @var array<string, bool> $visited */
        $visited = [];
        [$result,] = $this->resolveRef($ref, $document, $visited, $depth);

        if (false === $result instanceof Parameter) {
            throw new UnresolvableRefException(
                $ref,
                "Expected Parameter but got " . $result::class,
            );
        }

        return $result;
    }

    /**
     * @throws SchemaDepthExceededException
     */
    #[Override]
    public function resolveResponse(
        string $ref,
        OpenApiDocument $document,
        int $depth = 0,
    ): Response {
        /** @var array<string, bool> $visited */
        $visited = [];
        [$result,] = $this->resolveRef($ref, $document, $visited, $depth);

        if (false === $result instanceof Response) {
            throw new UnresolvableRefException(
                $ref,
                "Expected Response but got " . $result::class,
            );
        }

        return $result;
    }

    #[Override]
    public function schemaHasDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        int $depth = 0,
    ): bool {
        if (isset($this->hasDiscriminatorCache[$schema])) {
            /** @var WeakMap<OpenApiDocument, bool> $docCache */
            $docCache = $this->hasDiscriminatorCache[$schema];
        } else {
            /** @var WeakMap<OpenApiDocument, bool> $docCache */
            $docCache = new WeakMap();
            $this->hasDiscriminatorCache[$schema] = $docCache;
        }

        if (isset($docCache[$document])) {
            /** @var bool $cached */
            $cached = $docCache[$document];

            return $cached;
        }

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();
        [$has,] = $this->doSchemaHasDiscriminator($schema, $document, $visited, $depth);
        $docCache[$document] = $has;

        return $has;
    }

    #[Override]
    public function schemaHasRef(Schema $schema, int $depth = 0): bool
    {
        if (isset($this->hasRefCache[$schema])) {
            /** @var bool $cached */
            $cached = $this->hasRefCache[$schema];

            return $cached;
        }

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();
        [$has,] = $this->doSchemaHasRef($schema, $visited, $depth);
        $this->hasRefCache[$schema] = $has;

        return $has;
    }

    #[Override]
    public function resolveSchemaWithOverride(
        Schema $schema,
        OpenApiDocument $document,
    ): Schema {
        if (null === $schema->ref) {
            return $schema;
        }

        $resolved = $this->resolve($schema->ref, $document);

        $title = $schema->refSummary ?? $resolved->title;

        $description = $schema->refDescription ?? $resolved->description;

        return $resolved->withOverrides(
            title: $title,
            description: $description,
        );
    }

    #[Override]
    public function resolveParameterWithOverride(
        Parameter $parameter,
        OpenApiDocument $document,
    ): Parameter {
        if (null === $parameter->ref) {
            return $parameter;
        }

        $resolved = $this->resolveParameter($parameter->ref, $document);

        $description = $parameter->refDescription ?? $resolved->description;

        return new Parameter(
            ref: null,
            refSummary: null,
            refDescription: null,
            name: $resolved->name,
            in: $resolved->in,
            description: $description,
            required: $resolved->required,
            deprecated: $resolved->deprecated,
            allowEmptyValue: $resolved->allowEmptyValue,
            style: $resolved->style,
            explode: $resolved->explode,
            allowReserved: $resolved->allowReserved,
            schema: $resolved->schema,
            examples: $resolved->examples,
            example: $resolved->example,
            content: $resolved->content,
        );
    }

    #[Override]
    public function resolveResponseWithOverride(
        Response $response,
        OpenApiDocument $document,
    ): Response {
        if (null === $response->ref) {
            return $response;
        }

        $resolved = $this->resolveResponse($response->ref, $document);

        $summary = $response->refSummary ?? $resolved->summary;

        $description = $response->refDescription ?? $resolved->description;

        return new Response(
            ref: null,
            refSummary: null,
            refDescription: null,
            summary: $summary,
            description: $description,
            headers: $resolved->headers,
            content: $resolved->content,
            links: $resolved->links,
        );
    }

    /**
     * @param array<string, int|string|null> $base
     * @param array<string, int|string|null> $relative
     */
    private function resolveRelativeAgainstBase(array $base, array $relative, string $baseUri): string
    {
        $hadAuthority = isset($base['host']) || $this->baseUriHadAuthority($baseUri);

        if (isset($relative['host'])) {
            $base['host'] = $relative['host'];
            unset($base['user'], $base['pass'], $base['port']);
            $relativePathValue = $relative['path'] ?? '';
            $relativePath = is_string($relativePathValue) ? $relativePathValue : '';
            $base['path'] = $this->removeDotSegments($relativePath);
            $base = $this->replaceQueryAndFragment($base, $relative);
            return $this->buildUri($base, true);
        }

        $basePathValue = $base['path'] ?? '';
        $basePath = is_string($basePathValue) ? $basePathValue : '';
        $relativePathValue = $relative['path'] ?? '';
        $relativePath = is_string($relativePathValue) ? $relativePathValue : '';

        if ('' === $relativePath) {
            if (isset($relative['query'])) {
                $base['query'] = $relative['query'];
            }
            $base = $this->applyFragment($base, $relative);
            return $this->buildUri($base, $hadAuthority);
        }

        $base['path'] = $this->removeDotSegments($this->mergePaths($basePath, $relativePath));
        $base = $this->replaceQueryAndFragment($base, $relative);

        return $this->buildUri($base, $hadAuthority);
    }

    private function baseUriHadAuthority(string $baseUri): bool
    {
        $schemeEnd = strpos($baseUri, '://');
        return false !== $schemeEnd;
    }

    /**
     * @param array<string, int|string|null> $base
     * @param array<string, int|string|null> $relative
     *
     * @return array<string, int|string|null>
     */
    private function replaceQueryAndFragment(array $base, array $relative): array
    {
        if (isset($relative['query'])) {
            $base['query'] = $relative['query'];
        } else {
            unset($base['query']);
        }

        return $this->applyFragment($base, $relative);
    }

    /**
     * @param array<string, int|string|null> $base
     * @param array<string, int|string|null> $relative
     *
     * @return array<string, int|string|null>
     */
    private function applyFragment(array $base, array $relative): array
    {
        unset($base['fragment']);
        if (isset($relative['fragment'])) {
            $base['fragment'] = $relative['fragment'];
        }

        return $base;
    }

    /**
     * RFC 3986 §5.2.3 merge paths.
     */
    private function mergePaths(string $basePath, string $relativePath): string
    {
        if (str_starts_with($relativePath, '/')) {
            return $relativePath;
        }

        if ('' === $basePath) {
            return $relativePath;
        }

        $lastSlash = strrpos($basePath, '/');
        if (false === $lastSlash) {
            return $relativePath;
        }

        return substr($basePath, 0, $lastSlash + 1) . $relativePath;
    }

    /**
     * RFC 3986 §5.2.4 remove dot segments.
     */
    private function removeDotSegments(string $path): string
    {
        $input = $path;
        $output = '';

        while ('' !== $input) {
            if (str_starts_with($input, '../')) {
                $input = substr($input, 3);
                continue;
            }

            if (str_starts_with($input, './')) {
                $input = substr($input, 2);
                continue;
            }

            if (str_starts_with($input, '/./')) {
                $input = '/' . substr($input, 3);
                continue;
            }

            if ('/.' === $input) {
                $input = '/';
                continue;
            }

            if (str_starts_with($input, '/../')) {
                $input = '/' . substr($input, 4);
                $output = $this->removeLastSegment($output);
                continue;
            }

            if ('/..' === $input) {
                $input = '/';
                $output = $this->removeLastSegment($output);
                continue;
            }

            if ('.' === $input || '..' === $input) {
                $input = '';
                continue;
            }

            $moveOffset = $this->findNextSlashOffset($input);
            $output .= substr($input, 0, $moveOffset);
            $input = substr($input, $moveOffset);
        }

        return $output;
    }

    private function removeLastSegment(string $output): string
    {
        $lastSlash = strrpos($output, '/');
        if (false === $lastSlash) {
            return '';
        }

        return substr($output, 0, $lastSlash);
    }

    private function findNextSlashOffset(string $input): int
    {
        $nextSlash = strpos($input, '/', 1);
        if (false === $nextSlash) {
            return strlen($input);
        }

        return $nextSlash;
    }

    /**
     * @param array<string, int|string|null> $parts
     */
    private function buildUri(array $parts, bool $forceAuthority = false): string
    {
        $uri = '';

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : null;
        if (null !== $scheme) {
            $uri .= $scheme . ':';
        }

        $hasHost = isset($parts['host']) && is_string($parts['host']);
        $hostValue = $parts['host'] ?? null;
        $host = is_string($hostValue) ? $hostValue : '';

        if ($hasHost || $forceAuthority) {
            $uri .= '//';
            $userValue = $parts['user'] ?? null;
            $user = is_string($userValue) ? $userValue : null;
            $passValue = $parts['pass'] ?? null;
            $pass = is_string($passValue) ? $passValue : null;
            if (null !== $user) {
                $uri .= $user;
                if (null !== $pass) {
                    $uri .= ':' . $pass;
                }
                $uri .= '@';
            }
            if ($hasHost) {
                $uri .= $host;
            }
            $portValue = $parts['port'] ?? null;
            $port = is_int($portValue) ? $portValue : null;
            if (null !== $port) {
                $uri .= ':' . $port;
            }
        }

        $pathValue = $parts['path'] ?? null;
        $path = is_string($pathValue) ? $pathValue : null;
        if (null !== $path) {
            $uri .= $path;
        }

        $queryValue = $parts['query'] ?? null;
        $query = is_string($queryValue) ? $queryValue : null;
        if (null !== $query) {
            $uri .= '?' . $query;
        }

        $fragmentValue = $parts['fragment'] ?? null;
        $fragment = is_string($fragmentValue) ? $fragmentValue : null;
        if (null !== $fragment) {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    private function doSchemaHasDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        WeakMap $visited,
        int $depth,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ($visited->offsetExists($schema)) {
            return [false, $visited];
        }

        $visited[$schema] = true;

        if (null !== $schema->ref) {
            return $this->checkRefForDiscriminator(
                $schema->ref,
                $document,
                $visited,
                $depth + 1,
            );
        }

        if (null !== $schema->discriminator) {
            return [true, $visited];
        }

        foreach ($this->iterateSubSchemas($schema) as $subSchema) {
            [$has, $visited] = $this->doSchemaHasDiscriminator(
                $subSchema,
                $document,
                $visited,
                $depth + 1,
            );

            if ($has) {
                return [true, $visited];
            }
        }

        return [false, $visited];
    }

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    private function checkRefForDiscriminator(
        string $ref,
        OpenApiDocument $document,
        WeakMap $visited,
        int $depth,
    ): array {
        try {
            $resolvedSchema = $this->resolve($ref, $document);

            return $this->doSchemaHasDiscriminator(
                $resolvedSchema,
                $document,
                $visited,
                $depth,
            );
        } catch (UnresolvableRefException) {
            return [false, $visited];
        }
    }

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    private function doSchemaHasRef(Schema $schema, WeakMap $visited, int $depth): array
    {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ($visited->offsetExists($schema)) {
            return [false, $visited];
        }

        $visited[$schema] = true;

        if (null !== $schema->ref) {
            return [true, $visited];
        }

        foreach ($this->iterateSubSchemas($schema) as $subSchema) {
            [$has, $visited] = $this->doSchemaHasRef($subSchema, $visited, $depth + 1);

            if ($has) {
                return [true, $visited];
            }
        }

        return [false, $visited];
    }

    /**
     * @return Generator<int, Schema, void, void>
     */
    private function iterateSubSchemas(Schema $schema): Generator
    {
        yield from $schema->properties ?? [];
        yield from $schema->prefixItems ?? [];
        yield from $schema->allOf ?? [];
        yield from $schema->anyOf ?? [];
        yield from $schema->oneOf ?? [];
        yield from $schema->patternProperties ?? [];
        yield from $schema->dependentSchemas ?? [];

        foreach ($this->collectSingleSubSchemas($schema) as $subSchema) {
            yield $subSchema;
        }
    }

    /**
     * @return list<Schema>
     */
    private function collectSingleSubSchemas(Schema $schema): array
    {
        return array_values(array_filter([
            $schema->items,
            $schema->not,
            $schema->contains,
            $schema->propertyNames,
            $schema->if,
            $schema->then,
            $schema->else,
            $schema->unevaluatedItems,
            $schema->additionalProperties instanceof Schema ? $schema->additionalProperties : null,
            $schema->unevaluatedProperties instanceof Schema ? $schema->unevaluatedProperties : null,
            $schema->contentSchema instanceof Schema ? $schema->contentSchema : null,
        ], fn(?Schema $s): bool => null !== $s));
    }

    /**
     * @param array<string, bool> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{Schema|Parameter|Response, array<string, bool>}
     */
    private function resolveRef(
        string $ref,
        OpenApiDocument $document,
        array $visited,
        int $depth = 0,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if (false === str_starts_with($ref, "#/")) {
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

        if (isset($visited[$ref])) {
            throw new UnresolvableRefException(
                $ref,
                'Circular reference detected',
                internalTrace: $this->formatCircularPath($visited, $ref),
            );
        }

        $visited[$ref] = true;

        if (isset($this->cache[$document])) {
            /** @var RefCache $existing */
            $existing = $this->cache[$document];
            if (isset($existing->map[$ref])) {
                return [$existing->map[$ref], $visited];
            }
        }

        $path = substr($ref, self::REF_ROOT_PREFIX_LENGTH);
        $parts = explode("/", $path);

        try {
            $result = $this->navigate($document, $parts);
        } catch (UnresolvableRefException $e) {
            throw new UnresolvableRefException($ref, $e->reason, previous: $e);
        }

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited, $depth + 1);
        }

        if (isset($this->cache[$document])) {
            /** @var RefCache $refCache */
            $refCache = $this->cache[$document];
        } else {
            $refCache = new RefCache();
            $this->cache[$document] = $refCache;
        }
        $refCache->map[$ref] = $result;

        return [$result, $visited];
    }

    /**
     * @param array<int, string> $parts
     *
     * @throws SchemaDepthExceededException
     */
    private function navigate(
        object|array $current,
        array $parts,
        int $depth = 0,
    ): Schema|Parameter|Response {
        $count = count($parts);

        for ($i = 0; $i < $count; ++$i) {
            if ($depth + $i >= ValidationContext::MAX_DEPTH) {
                throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
            }

            $current = $this->getProperty($current, $parts[$i]);
        }

        if (
            $current instanceof Schema
            || $current instanceof Parameter
            || $current instanceof Response
        ) {
            return $current;
        }

        throw new UnresolvableRefException(
            "",
            "Target is not a Schema, Parameter, or Response",
        );
    }

    private function getProperty(
        object|array $container,
        string $property,
    ): object|array {
        $value = match (true) {
            is_array($container) => array_key_exists($property, $container)
                ? $container[$property]
                : throw new UnresolvableRefException($property, "Array key does not exist"),
            property_exists($container, $property) => $container->$property,
            default => throw new UnresolvableRefException($property, "Property does not exist"),
        };

        if (null === $value) {
            throw new UnresolvableRefException($property, "Value is null");
        }

        if (false === is_object($value) && false === is_array($value)) {
            throw new UnresolvableRefException(
                $property,
                "Value is not an object or array",
            );
        }

        return $value;
    }

    /**
     * @param array<string, bool> $visited
     */
    private function formatCircularPath(
        array $visited,
        string $circularRef,
    ): string {
        $path = array_keys($visited);
        $path[] = $circularRef;
        return implode(" -> ", $path);
    }
}
