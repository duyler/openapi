<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Override;
use WeakMap;

use function array_key_exists;
use function is_array;
use function is_object;

class RefResolver implements RefResolverInterface
{
    private WeakMap $cache;

    public function __construct()
    {
        $this->cache = new WeakMap();
    }

    #[Override]
    public function resolve(string $ref, OpenApiDocument $document): Schema
    {
        $result = $this->resolveRef($ref, $document);

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
        $result = $this->resolveRef($ref, $document);

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
        $result = $this->resolveRef($ref, $document);

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

    private function resolveRef(string $ref, OpenApiDocument $document): Schema|Parameter|Response
    {
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
}
