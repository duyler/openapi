<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RefCache;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakMap;

/**
 * @internal
 *
 * Anti-test for P-045: asserts that the WeakMap cache entry is a
 * mutable RefCache container whose spl_object_id stays stable as
 * additional $refs are resolved. Reverting to copy-on-write (storing
 * plain arrays under the document key) breaks every assertion here:
 * the value is no longer a RefCache, and re-writing the array on each
 * resolve yields a different identity.
 */
final class RefResolverCachePerfTest extends TestCase
{
    #[Test]
    public function resolve_ref_does_not_copy_cache_array(): void
    {
        $schemas = [];

        for ($i = 0; $i < 100; ++$i) {
            $schemas['schema' . $i] = new Schema(
                type: 'object',
                properties: ['value' => new Schema(type: 'string')],
            );
        }

        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(schemas: $schemas),
        );

        $resolver = new RefResolver();

        for ($i = 0; $i < 100; ++$i) {
            $resolver->resolve('#/components/schemas/schema' . $i, $document);
        }

        $cache = $this->readCache($resolver);

        self::assertTrue(
            isset($cache[$document]),
            'resolveRef must populate WeakMap cache for the document',
        );

        $entry = $cache[$document];

        self::assertInstanceOf(
            RefCache::class,
            $entry,
            'Cache entry must be a mutable RefCache container, not a plain array (P-045)',
        );

        $idBefore = spl_object_id($entry);

        $resolver->resolve('#/components/schemas/schema0', $document);

        $entryAfter = $this->readCache($resolver)[$document];
        $idAfter = spl_object_id($entryAfter);

        self::assertSame(
            $idBefore,
            $idAfter,
            'RefCache container identity must remain stable across resolves (no copy-on-write)',
        );

        self::assertCount(100, $entryAfter->map, 'All 100 resolved refs must be cached');
    }

    #[Test]
    public function ref_cache_class_holds_resolved_targets(): void
    {
        $refCache = new RefCache();

        $schema = new Schema(type: 'string');

        $refCache->map['#/components/schemas/Foo'] = $schema;

        self::assertSame($schema, $refCache->map['#/components/schemas/Foo']);
        self::assertCount(1, $refCache->map);
    }

    private function readCache(RefResolver $resolver): WeakMap
    {
        $property = new ReflectionProperty($resolver, 'cache');

        /** @var WeakMap $cache */
        return $property->getValue($resolver);
    }
}
