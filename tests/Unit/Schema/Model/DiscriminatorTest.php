<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Discriminator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Discriminator::class)]
final class DiscriminatorTest extends TestCase
{
    #[Test]
    public function can_create_discriminator(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: ['pet' => '#/components/schemas/Pet'],
        );

        self::assertSame('type', $discriminator->propertyName);
        self::assertSame(['pet' => '#/components/schemas/Pet'], $discriminator->mapping);
    }

    #[Test]
    public function can_create_discriminator_with_null_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: null,
        );

        self::assertSame('type', $discriminator->propertyName);
        self::assertNull($discriminator->mapping);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: ['pet' => '#/components/schemas/Pet'],
        );

        $serialized = $discriminator->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('propertyName', $serialized);
        self::assertArrayHasKey('mapping', $serialized);
        self::assertSame('type', $serialized['propertyName']);
    }

    #[Test]
    public function json_serialize_excludes_null_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: null,
        );

        $serialized = $discriminator->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('propertyName', $serialized);
        self::assertArrayNotHasKey('mapping', $serialized);
    }

    #[Test]
    public function discriminator_has_default_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: ['dog' => '#/components/schemas/Dog'],
            defaultMapping: '#/components/schemas/Pet',
        );

        self::assertSame('#/components/schemas/Pet', $discriminator->defaultMapping);
    }

    #[Test]
    public function json_serialize_includes_default_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: ['dog' => '#/components/schemas/Dog'],
            defaultMapping: '#/components/schemas/Pet',
        );

        $serialized = $discriminator->jsonSerialize();

        self::assertArrayHasKey('defaultMapping', $serialized);
        self::assertSame('#/components/schemas/Pet', $serialized['defaultMapping']);
    }

    #[Test]
    public function json_serialize_excludes_null_default_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: 'type',
            mapping: null,
            defaultMapping: null,
        );

        $serialized = $discriminator->jsonSerialize();

        self::assertArrayNotHasKey('defaultMapping', $serialized);
    }

    #[Test]
    public function can_create_discriminator_without_property_name(): void
    {
        $discriminator = new Discriminator(
            defaultMapping: '#/components/schemas/Fallback',
        );

        self::assertNull($discriminator->propertyName);
        self::assertSame('#/components/schemas/Fallback', $discriminator->defaultMapping);
    }

    #[Test]
    public function can_create_discriminator_with_only_default_mapping(): void
    {
        $discriminator = new Discriminator(
            propertyName: null,
            mapping: null,
            defaultMapping: '#/components/schemas/Fallback',
        );

        self::assertNull($discriminator->propertyName);
        self::assertNull($discriminator->mapping);
        self::assertSame('#/components/schemas/Fallback', $discriminator->defaultMapping);
    }

    #[Test]
    public function json_serialize_excludes_null_property_name(): void
    {
        $discriminator = new Discriminator(
            defaultMapping: '#/components/schemas/Fallback',
        );

        $serialized = $discriminator->jsonSerialize();

        self::assertArrayNotHasKey('propertyName', $serialized);
        self::assertArrayHasKey('defaultMapping', $serialized);
    }

    #[Test]
    public function can_create_empty_discriminator(): void
    {
        $discriminator = new Discriminator();

        self::assertNull($discriminator->propertyName);
        self::assertNull($discriminator->mapping);
        self::assertNull($discriminator->defaultMapping);
    }

    #[Test]
    public function json_serialize_empty_discriminator_returns_empty_array(): void
    {
        $discriminator = new Discriminator();

        $serialized = $discriminator->jsonSerialize();

        self::assertSame([], $serialized);
    }
}
