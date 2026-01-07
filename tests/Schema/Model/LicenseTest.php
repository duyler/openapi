<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\License;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\License
 */
final class LicenseTest extends TestCase
{
    #[Test]
    public function can_create_license_with_all_fields(): void
    {
        $license = new License(
            name: 'MIT',
            identifier: 'MIT',
            url: 'https://opensource.org/licenses/MIT',
        );

        self::assertSame('MIT', $license->name);
        self::assertSame('MIT', $license->identifier);
        self::assertSame('https://opensource.org/licenses/MIT', $license->url);
    }

    #[Test]
    public function can_create_license_with_null_fields(): void
    {
        $license = new License(
            name: 'MIT',
            identifier: null,
            url: null,
        );

        self::assertSame('MIT', $license->name);
        self::assertNull($license->identifier);
        self::assertNull($license->url);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $license = new License(
            name: 'MIT',
            identifier: 'MIT',
            url: 'https://opensource.org/licenses/MIT',
        );

        $serialized = $license->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('identifier', $serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertSame('MIT', $serialized['name']);
        self::assertSame('MIT', $serialized['identifier']);
        self::assertSame('https://opensource.org/licenses/MIT', $serialized['url']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $license = new License(
            name: 'MIT',
            identifier: null,
            url: null,
        );

        $serialized = $license->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayNotHasKey('identifier', $serialized);
        self::assertArrayNotHasKey('url', $serialized);
    }
}
