<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Exception\RefResolutionException;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Override;
use WeakMap;

use function array_key_exists;
use function dirname;
use function is_array;
use function is_object;
use function str_starts_with;

final class RefResolver implements RefResolverInterface
{
    private WeakMap $cache;

    public function __construct()
    {
        $this->cache = new WeakMap();
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
        $basePath = dirname($baseUri);

        return $basePath . '/' . $relativeRef;
    }

    #[Override]
    public function resolve(string $ref, OpenApiDocument $document): Schema
    {
        /** @var array<string, bool> $visited */
        $visited = [];
        $result = $this->resolveRef($ref, $document, $visited);

        if (false === $result instanceof Schema) {
            throw new UnresolvableRefException(
                $ref,
                'Expected Schema but got ' . $result::class,
            );
        }

        return $result;
    }

    #[Override]
    public function resolveParameter(string $ref, OpenApiDocument $document): Parameter
    {
        /** @var array<string, bool> $visited */
        $visited = [];
        $result = $this->resolveRef($ref, $document, $visited);

        if (false === $result instanceof Parameter) {
            throw new UnresolvableRefException(
                $ref,
                'Expected Parameter but got ' . $result::class,
            );
        }

        return $result;
    }

    #[Override]
    public function resolveResponse(string $ref, OpenApiDocument $document): Response
    {
        /** @var array<string, bool> $visited */
        $visited = [];
        $result = $this->resolveRef($ref, $document, $visited);

        if (false === $result instanceof Response) {
            throw new UnresolvableRefException(
                $ref,
                'Expected Response but got ' . $result::class,
            );
        }

        return $result;
    }

    #[Override]
    public function schemaHasDiscriminator(Schema $schema, OpenApiDocument $document, array &$visited = []): bool
    {
        $schemaId = spl_object_id($schema);

        if (isset($visited[$schemaId])) {
            return false;
        }

        $visited[$schemaId] = true;

        if (null !== $schema->ref) {
            try {
                $resolvedSchema = $this->resolve($schema->ref, $document);
                return $this->schemaHasDiscriminator($resolvedSchema, $document, $visited);
            } catch (UnresolvableRefException) {
                return false;
            }
        }

        if (null !== $schema->discriminator) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $property) {
                if ($this->schemaHasDiscriminator($property, $document, $visited)) {
                    return true;
                }
            }
        }

        if (null !== $schema->items) {
            return $this->schemaHasDiscriminator($schema->items, $document, $visited);
        }

        if (null !== $schema->oneOf) {
            foreach ($schema->oneOf as $subSchema) {
                if ($this->schemaHasDiscriminator($subSchema, $document, $visited)) {
                    return true;
                }
            }
        }

        if (null !== $schema->anyOf) {
            foreach ($schema->anyOf as $subSchema) {
                if ($this->schemaHasDiscriminator($subSchema, $document, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    #[Override]
    public function resolveSchemaWithOverride(Schema $schema, OpenApiDocument $document): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        $resolved = $this->resolve($schema->ref, $document);

        $description = $resolved->description;
        if (null !== $schema->refDescription) {
            $description = $schema->refDescription;
        }

        $title = $resolved->title;
        if (null !== $schema->refSummary) {
            $title = $schema->refSummary;
        }

        return new Schema(
            ref: null,
            refSummary: null,
            refDescription: null,
            format: $resolved->format,
            title: $title,
            description: $description,
            default: $resolved->default,
            deprecated: $resolved->deprecated,
            type: $resolved->type,
            nullable: $resolved->nullable,
            const: $resolved->const,
            multipleOf: $resolved->multipleOf,
            maximum: $resolved->maximum,
            exclusiveMaximum: $resolved->exclusiveMaximum,
            minimum: $resolved->minimum,
            exclusiveMinimum: $resolved->exclusiveMinimum,
            maxLength: $resolved->maxLength,
            minLength: $resolved->minLength,
            pattern: $resolved->pattern,
            maxItems: $resolved->maxItems,
            minItems: $resolved->minItems,
            uniqueItems: $resolved->uniqueItems,
            maxProperties: $resolved->maxProperties,
            minProperties: $resolved->minProperties,
            required: $resolved->required,
            allOf: $resolved->allOf,
            anyOf: $resolved->anyOf,
            oneOf: $resolved->oneOf,
            not: $resolved->not,
            discriminator: $resolved->discriminator,
            properties: $resolved->properties,
            additionalProperties: $resolved->additionalProperties,
            unevaluatedProperties: $resolved->unevaluatedProperties,
            items: $resolved->items,
            prefixItems: $resolved->prefixItems,
            contains: $resolved->contains,
            minContains: $resolved->minContains,
            maxContains: $resolved->maxContains,
            patternProperties: $resolved->patternProperties,
            propertyNames: $resolved->propertyNames,
            dependentSchemas: $resolved->dependentSchemas,
            if: $resolved->if,
            then: $resolved->then,
            else: $resolved->else,
            unevaluatedItems: $resolved->unevaluatedItems,
            example: $resolved->example,
            examples: $resolved->examples,
            enum: $resolved->enum,
            contentEncoding: $resolved->contentEncoding,
            contentMediaType: $resolved->contentMediaType,
            contentSchema: $resolved->contentSchema,
            jsonSchemaDialect: $resolved->jsonSchemaDialect,
            xml: $resolved->xml,
        );
    }

    #[Override]
    public function resolveParameterWithOverride(Parameter $parameter, OpenApiDocument $document): Parameter
    {
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
    public function resolveResponseWithOverride(Response $response, OpenApiDocument $document): Response
    {
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
     * @param array<string, bool> $visited
     */
    private function resolveRef(string $ref, OpenApiDocument $document, array &$visited): Schema|Parameter|Response
    {
        if (isset($visited[$ref])) {
            throw new UnresolvableRefException(
                $ref,
                'Circular reference detected: ' . $this->formatCircularPath($visited, $ref),
            );
        }

        $visited[$ref] = true;

        if (isset($this->cache[$document])) {
            /** @var array<string, Schema|Parameter|Response> */
            $cacheEntry = $this->cache[$document];
            if (isset($cacheEntry[$ref])) {
                return $cacheEntry[$ref];
            }
        }

        if (false === str_starts_with($ref, '#/')) {
            throw new UnresolvableRefException($ref, 'Only local refs (#/...) are supported');
        }

        $path = substr($ref, 2);
        $parts = explode('/', $path);

        try {
            $result = $this->navigate($document, $parts);
        } catch (UnresolvableRefException $e) {
            throw new UnresolvableRefException($ref, $e->reason, previous: $e);
        }

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited);
        }

        /** @var array<string, Schema|Parameter|Response> */
        $cacheArray = $this->cache[$document] ?? [];
        $cacheArray[$ref] = $result;
        $this->cache[$document] = $cacheArray;

        return $result;
    }

    /**
     * @param array<int, string> $parts
     */
    private function navigate(object|array $current, array $parts): Schema|Parameter|Response
    {
        $part = array_shift($parts);

        if (null === $part) {
            if ($current instanceof Schema || $current instanceof Parameter || $current instanceof Response) {
                return $current;
            }

            throw new UnresolvableRefException(
                '',
                'Target is not a Schema, Parameter, or Response',
            );
        }

        $next = $this->getProperty($current, $part);

        return $this->navigate($next, $parts);
    }

    private function getProperty(object|array $container, string $property): object|array
    {
        if (is_array($container)) {
            if (false === array_key_exists($property, $container)) {
                throw new UnresolvableRefException(
                    $property,
                    'Array key does not exist',
                );
            }

            $value = $container[$property];
        } else {
            if (false === property_exists($container, $property)) {
                throw new UnresolvableRefException(
                    $property,
                    'Property does not exist',
                );
            }

            $value = $container->$property;
        }

        if (null === $value) {
            throw new UnresolvableRefException(
                $property,
                'Value is null',
            );
        }

        if (false === is_object($value) && false === is_array($value)) {
            throw new UnresolvableRefException(
                $property,
                'Value is not an object or array',
            );
        }

        return $value;
    }

    /**
     * @param array<string, bool> $visited
     */
    private function formatCircularPath(array $visited, string $circularRef): string
    {
        $path = array_keys($visited);
        $path[] = $circularRef;
        return implode(' -> ', $path);
    }
}
