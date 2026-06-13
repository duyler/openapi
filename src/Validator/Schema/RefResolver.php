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
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Override;
use WeakMap;

use Generator;

use function array_key_exists;
use function dirname;
use function is_array;
use function is_object;
use function str_starts_with;

final class RefResolver implements RefResolverInterface
{
    private const int REF_ROOT_PREFIX_LENGTH = 2;

    private WeakMap $cache;

    public function __construct()
    {
        $this->cache = new WeakMap();
    }

    #[Override]
    public function clear(): void
    {
        $this->cache = new WeakMap();
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
        $basePath = dirname($baseUri);

        return $basePath . "/" . $relativeRef;
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
        [$has,] = $this->doSchemaHasDiscriminator($schema, $document, [], $depth);

        return $has;
    }

    #[Override]
    public function schemaHasRef(Schema $schema, int $depth = 0): bool
    {
        [$has,] = $this->doSchemaHasRef($schema, [], $depth);

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

        $title = null !== $schema->refSummary ? $schema->refSummary : $resolved->title;

        $description = null !== $schema->refDescription ? $schema->refDescription : $resolved->description;

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

        $description = $resolved->description;
        if (null !== $parameter->refDescription) {
            $description = $parameter->refDescription;
        }

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

        $summary = $resolved->summary;
        if (null !== $response->refSummary) {
            $summary = $response->refSummary;
        }

        $description = $resolved->description;
        if (null !== $response->refDescription) {
            $description = $response->refDescription;
        }

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
     * @param array<int, bool> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, array<int, bool>}
     */
    private function doSchemaHasDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        array $visited,
        int $depth,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        $schemaId = spl_object_id($schema);

        if (isset($visited[$schemaId])) {
            return [false, $visited];
        }

        $visited[$schemaId] = true;

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
     * @param array<int, bool> $visited
     *
     * @return array{bool, array<int, bool>}
     */
    private function checkRefForDiscriminator(
        string $ref,
        OpenApiDocument $document,
        array $visited,
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
     * @param array<int, bool> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, array<int, bool>}
     */
    private function doSchemaHasRef(Schema $schema, array $visited, int $depth): array
    {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        $schemaId = spl_object_id($schema);

        if (isset($visited[$schemaId])) {
            return [false, $visited];
        }

        $visited[$schemaId] = true;

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

        if (isset($visited[$ref])) {
            throw new UnresolvableRefException(
                $ref,
                "Circular reference detected: "
                    . $this->formatCircularPath($visited, $ref),
            );
        }

        $visited[$ref] = true;

        if (isset($this->cache[$document])) {
            /** @var array<string, Schema|Parameter|Response> */
            $cacheEntry = $this->cache[$document];
            if (isset($cacheEntry[$ref])) {
                return [$cacheEntry[$ref], $visited];
            }
        }

        if (false === str_starts_with($ref, "#/")) {
            throw new UnresolvableRefException(
                $ref,
                "Only local refs (#/...) are supported",
            );
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

        /** @var array<string, Schema|Parameter|Response> */
        $cacheArray = $this->cache[$document] ?? [];
        $cacheArray[$ref] = $result;
        $this->cache[$document] = $cacheArray;

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
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        $part = array_shift($parts);

        if (null === $part) {
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

        $next = $this->getProperty($current, $part);

        return $this->navigate($next, $parts, $depth + 1);
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
