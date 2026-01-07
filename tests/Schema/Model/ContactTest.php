<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Contact;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Contact
 */
final class ContactTest extends TestCase
{
    #[Test]
    public function can_create_contact_with_all_fields(): void
    {
        $contact = new Contact(
            name: 'John Doe',
            email: 'john@example.com',
            url: 'https://example.com',
        );

        self::assertSame('John Doe', $contact->name);
        self::assertSame('john@example.com', $contact->email);
        self::assertSame('https://example.com', $contact->url);
    }

    #[Test]
    public function can_create_contact_with_null_fields(): void
    {
        $contact = new Contact(
            name: null,
            email: null,
            url: null,
        );

        self::assertNull($contact->name);
        self::assertNull($contact->email);
        self::assertNull($contact->url);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $contact = new Contact(
            name: 'John Doe',
            email: 'john@example.com',
            url: 'https://example.com',
        );

        $serialized = $contact->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('email', $serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertSame('John Doe', $serialized['name']);
        self::assertSame('john@example.com', $serialized['email']);
        self::assertSame('https://example.com', $serialized['url']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $contact = new Contact(
            name: null,
            email: null,
            url: null,
        );

        $serialized = $contact->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('name', $serialized);
        self::assertArrayNotHasKey('email', $serialized);
        self::assertArrayNotHasKey('url', $serialized);
    }
}
