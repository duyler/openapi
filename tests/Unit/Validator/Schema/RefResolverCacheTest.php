<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakMap;

/**
 * @internal
 */
final class RefResolverCacheTest extends TestCase
{
    #[Test]
    public function schema_has_discriminator_cached_via_weakmap(): void
    {
        $resolver = new RefResolver();

        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => new Schema(
                        type: 'object',
                        properties: ['name' => new Schema(type: 'string')],
                    ),
                ],
            ),
        );

        $schema = new Schema(
            type: 'object',
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $result = $resolver->schemaHasDiscriminator($schema, $document);

        $docCache = $this->readDiscriminatorCache($resolver);

        self::assertTrue($result);
        self::assertTrue(
            isset($docCache[$schema]),
            'schemaHasDiscriminator must populate WeakMap cache for the schema',
        );

        /** @var WeakMap $inner */
        $inner = $docCache[$schema];
        self::assertTrue(
            isset($inner[$document]),
            'schemaHasDiscriminator must populate per-document cache entry',
        );
    }

    #[Test]
    public function schema_has_discriminator_cache_isolated_per_document(): void
    {
        $resolver = new RefResolver();

        $documentA = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );

        $documentB = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Other', version: '2.0.0'),
        );

        $schema = new Schema(
            discriminator: new Discriminator(propertyName: 'kind'),
        );

        $resolver->schemaHasDiscriminator($schema, $documentA);
        $resolver->schemaHasDiscriminator($schema, $documentB);

        $docCache = $this->readDiscriminatorCache($resolver);
        /** @var WeakMap $inner */
        $inner = $docCache[$schema];

        self::assertTrue(isset($inner[$documentA]));
        self::assertTrue(isset($inner[$documentB]));
    }

    #[Test]
    public function schema_has_ref_cached_via_weakmap(): void
    {
        $resolver = new RefResolver();

        $schemaWithRef = new Schema(
            properties: [
                'leaf' => new Schema(ref: '#/components/schemas/Anything'),
            ],
        );

        $result = $resolver->schemaHasRef($schemaWithRef);
        $cache = $this->readRefCache($resolver);

        self::assertTrue($result);
        self::assertTrue(
            isset($cache[$schemaWithRef]),
            'schemaHasRef must populate WeakMap cache for the schema',
        );
    }

    #[Test]
    public function clear_invalidates_caches(): void
    {
        $resolver = new RefResolver();

        $schema = new Schema(
            discriminator: new Discriminator(propertyName: 'kind'),
        );
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );

        $resolver->schemaHasDiscriminator($schema, $document);
        $resolver->clear();

        $docCache = $this->readDiscriminatorCache($resolver);

        self::assertFalse(
            isset($docCache[$schema]),
            'clear() must invalidate the discriminator cache',
        );
    }

    private function readDiscriminatorCache(RefResolver $resolver): WeakMap
    {
        $property = new ReflectionProperty($resolver, 'hasDiscriminatorCache');
        /** @var WeakMap $cache */
        $cache = $property->getValue($resolver);

        return $cache;
    }

    private function readRefCache(RefResolver $resolver): WeakMap
    {
        $property = new ReflectionProperty($resolver, 'hasRefCache');
        /** @var WeakMap $cache */
        $cache = $property->getValue($resolver);

        return $cache;
    }
}
