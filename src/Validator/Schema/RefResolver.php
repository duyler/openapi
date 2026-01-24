<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

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
        if (isset($this->cache[$document])) {
            /** @var array<string, Schema> */
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
            $schema = $this->navigate($document, $parts);
        } catch (UnresolvableRefException $e) {
            throw new UnresolvableRefException($ref, $e->reason, previous: $e);
        }

        /** @var array<string, Schema> */
        $cacheArray = $this->cache[$document] ?? [];
        $cacheArray[$ref] = $schema;
        $this->cache[$document] = $cacheArray;

        return $schema;
    }

    /**
     * @param array<int, string> $parts
     */
    private function navigate(object|array $current, array $parts): Schema
    {
        $part = array_shift($parts);

        if (null === $part) {
            if (false === $current instanceof Schema) {
                throw new UnresolvableRefException(
                    '',
                    'Target is not a Schema',
                );
            }

            return $current;
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
