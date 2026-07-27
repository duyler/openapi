<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\Internal\DiscriminatorDetector;
use Duyler\OpenApi\Validator\Schema\Internal\DocumentNavigator;
use Duyler\OpenApi\Validator\Schema\Internal\UriResolver;
use Override;
use WeakMap;

final class RefResolver implements RefResolverInterface
{
    /** @var WeakMap<OpenApiDocument, RefCache> */
    private WeakMap $cache;

    /** @var WeakMap<Schema, WeakMap<OpenApiDocument, bool>> */
    private WeakMap $hasDiscriminatorCache;

    /** @var WeakMap<Schema, bool> */
    private WeakMap $hasRefCache;

    private readonly FileExternalRefResolver $builtinFileResolver;
    private readonly DocumentNavigator $navigator;

    public function __construct(
        private readonly ?ExternalRefResolverInterface $externalRefResolver = null,
        ?FileExternalRefResolver $builtinFileResolver = null,
        private readonly UriResolver $uriResolver = new UriResolver(),
        private readonly DiscriminatorDetector $discriminatorDetector = new DiscriminatorDetector(),
    ) {
        $this->clear();
        $this->builtinFileResolver = $builtinFileResolver ?? new FileExternalRefResolver();
        $this->navigator = new DocumentNavigator(
            builtinFileResolver: $this->builtinFileResolver,
            externalRefResolver: $this->externalRefResolver,
        );
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
    public function resolveRelativeRef(string $ref, OpenApiDocument $document): string
    {
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

        return $this->uriResolver->resolveRelativeAgainstBase($base, $relative, $baseUri);
    }

    #[Override]
    public function resolve(string $ref, OpenApiDocument $document, int $depth = 0): Schema
    {
        [$result,] = $this->navigator->resolveRef($ref, $document, [], $this->cache, $depth);
        if (false === $result instanceof Schema) {
            throw new UnresolvableRefException($ref, 'Expected Schema but got ' . $result::class);
        }

        return $result;
    }

    #[Override]
    public function resolveParameter(string $ref, OpenApiDocument $document, int $depth = 0): Parameter
    {
        [$result,] = $this->navigator->resolveRef($ref, $document, [], $this->cache, $depth);
        if (false === $result instanceof Parameter) {
            throw new UnresolvableRefException($ref, 'Expected Parameter but got ' . $result::class);
        }

        return $result;
    }

    #[Override]
    public function resolveResponse(string $ref, OpenApiDocument $document, int $depth = 0): Response
    {
        [$result,] = $this->navigator->resolveRef($ref, $document, [], $this->cache, $depth);
        if (false === $result instanceof Response) {
            throw new UnresolvableRefException($ref, 'Expected Response but got ' . $result::class);
        }

        return $result;
    }

    #[Override]
    public function schemaHasDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        int $depth = 0,
    ): bool {
        /** @var WeakMap<OpenApiDocument, bool> $docCache */
        $docCache = $this->hasDiscriminatorCache[$schema] ?? new WeakMap();
        $this->hasDiscriminatorCache[$schema] = $docCache;

        if (isset($docCache[$document])) {
            /** @var bool $cached */
            $cached = $docCache[$document];

            return $cached;
        }

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();
        [$has,] = $this->discriminatorDetector->detectDiscriminator(
            $schema,
            $document,
            $visited,
            $this->resolve(...),
            $depth,
        );
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
        [$has,] = $this->discriminatorDetector->detectRef($schema, $visited, $depth);
        $this->hasRefCache[$schema] = $has;

        return $has;
    }

    #[Override]
    public function resolveSchemaWithOverride(Schema $schema, OpenApiDocument $document): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->resolve($schema->ref, $document)->withSibling($schema);
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

        return new Parameter(
            ref: null,
            refSummary: null,
            refDescription: null,
            name: $resolved->name,
            in: $resolved->in,
            description: $parameter->refDescription ?? $resolved->description,
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

        return new Response(
            ref: null,
            refSummary: null,
            refDescription: null,
            summary: $response->refSummary ?? $resolved->summary,
            description: $response->refDescription ?? $resolved->description,
            headers: $resolved->headers,
            content: $resolved->content,
            links: $resolved->links,
        );
    }
}
