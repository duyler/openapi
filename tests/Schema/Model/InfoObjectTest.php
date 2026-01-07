<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\InfoObject
 */
final class InfoObjectTest extends TestCase
{
    #[Test]
    public function can_create_info_object_with_required_fields(): void
    {
        $info = new InfoObject(
            title: 'Test API',
            version: '1.0.0',
        );

        self::assertSame('Test API', $info->title);
        self::assertSame('1.0.0', $info->version);
        self::assertNull($info->description);
        self::assertNull($info->termsOfService);
        self::assertNull($info->contact);
        self::assertNull($info->license);
    }

    #[Test]
    public function can_create_info_object_with_all_fields(): void
    {
        $contact = new Contact(
            name: 'John Doe',
            email: 'john@example.com',
            url: null,
        );

        $license = new License(
            name: 'MIT',
            identifier: null,
            url: 'https://opensource.org/licenses/MIT',
        );

        $info = new InfoObject(
            title: 'Test API',
            version: '1.0.0',
            description: 'Test description',
            termsOfService: 'https://example.com/terms',
            contact: $contact,
            license: $license,
        );

        self::assertSame('Test API', $info->title);
        self::assertSame('1.0.0', $info->version);
        self::assertSame('Test description', $info->description);
        self::assertSame('https://example.com/terms', $info->termsOfService);
        self::assertSame($contact, $info->contact);
        self::assertSame($license, $info->license);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $contact = new Contact(
            name: 'John Doe',
            email: null,
            url: null,
        );

        $license = new License(
            name: 'MIT',
            identifier: null,
            url: null,
        );

        $info = new InfoObject(
            title: 'Test API',
            version: '1.0.0',
            description: 'Test description',
            termsOfService: 'https://example.com/terms',
            contact: $contact,
            license: $license,
        );

        $serialized = $info->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('title', $serialized);
        self::assertArrayHasKey('version', $serialized);
        self::assertSame('Test API', $serialized['title']);
        self::assertSame('1.0.0', $serialized['version']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $info = new InfoObject(
            title: 'Test API',
            version: '1.0.0',
        );

        $serialized = $info->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('title', $serialized);
        self::assertArrayHasKey('version', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
    }
}
