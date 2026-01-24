<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

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
}
