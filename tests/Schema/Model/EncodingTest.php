<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encoding::class)]
final class EncodingTest extends TestCase
{
    #[Test]
    public function can_create_encoding_with_all_fields(): void
    {
        $headers = new Headers(['X-Custom' => new Header(description: 'Custom header')]);
        $encoding = new Encoding(
            contentType: 'application/json',
            headers: $headers,
            style: 'form',
            explode: true,
            allowReserved: false,
        );

        self::assertSame('application/json', $encoding->contentType);
        self::assertInstanceOf(Headers::class, $encoding->headers);
        self::assertSame('form', $encoding->style);
        self::assertTrue($encoding->explode);
        self::assertFalse($encoding->allowReserved);
    }

    #[Test]
    public function can_create_encoding_with_null_fields(): void
    {
        $encoding = new Encoding();

        self::assertNull($encoding->contentType);
        self::assertNull($encoding->headers);
        self::assertNull($encoding->style);
        self::assertNull($encoding->explode);
        self::assertNull($encoding->allowReserved);
        self::assertNull($encoding->encoding);
        self::assertNull($encoding->prefixEncoding);
        self::assertNull($encoding->itemEncoding);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $encoding = new Encoding(
            contentType: 'application/json',
            style: 'form',
            explode: true,
        );

        $serialized = $encoding->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contentType', $serialized);
        self::assertArrayHasKey('style', $serialized);
        self::assertArrayHasKey('explode', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $encoding = new Encoding();

        $serialized = $encoding->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertEmpty($serialized);
    }

    #[Test]
    public function supports_nested_encoding(): void
    {
        $nestedEncoding = new Encoding(
            contentType: 'text/plain',
        );

        $encoding = new Encoding(
            contentType: 'multipart/form-data',
            encoding: ['field1' => $nestedEncoding],
        );

        self::assertNotNull($encoding->encoding);
        self::assertArrayHasKey('field1', $encoding->encoding);
        self::assertSame('text/plain', $encoding->encoding['field1']->contentType);
    }

    #[Test]
    public function supports_prefix_encoding(): void
    {
        $prefixEncoding1 = new Encoding(contentType: 'application/json');
        $prefixEncoding2 = new Encoding(contentType: 'text/plain');

        $encoding = new Encoding(
            contentType: 'multipart/mixed',
            prefixEncoding: [$prefixEncoding1, $prefixEncoding2],
        );

        self::assertNotNull($encoding->prefixEncoding);
        self::assertCount(2, $encoding->prefixEncoding);
        self::assertSame('application/json', $encoding->prefixEncoding[0]->contentType);
        self::assertSame('text/plain', $encoding->prefixEncoding[1]->contentType);
    }

    #[Test]
    public function supports_item_encoding(): void
    {
        $itemEncoding = new Encoding(
            contentType: 'application/json',
        );

        $encoding = new Encoding(
            contentType: 'application/jsonl',
            itemEncoding: $itemEncoding,
        );

        self::assertNotNull($encoding->itemEncoding);
        self::assertSame('application/json', $encoding->itemEncoding->contentType);
    }

    #[Test]
    public function json_serialize_includes_nested_encoding(): void
    {
        $nestedEncoding = new Encoding(contentType: 'text/plain');
        $encoding = new Encoding(
            contentType: 'multipart/form-data',
            encoding: ['field1' => $nestedEncoding],
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('encoding', $serialized);
        self::assertArrayHasKey('field1', $serialized['encoding']);
    }

    #[Test]
    public function json_serialize_includes_prefix_encoding(): void
    {
        $encoding = new Encoding(
            contentType: 'multipart/mixed',
            prefixEncoding: [new Encoding(contentType: 'application/json')],
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('prefixEncoding', $serialized);
    }

    #[Test]
    public function json_serialize_includes_item_encoding(): void
    {
        $encoding = new Encoding(
            contentType: 'application/jsonl',
            itemEncoding: new Encoding(contentType: 'application/json'),
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('itemEncoding', $serialized);
    }

    #[Test]
    public function json_serialize_includes_headers(): void
    {
        $headers = new Headers(['X-Custom' => new Header(description: 'Test')]);
        $encoding = new Encoding(
            contentType: 'application/json',
            headers: $headers,
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('headers', $serialized);
    }

    #[Test]
    public function json_serialize_includes_allow_reserved(): void
    {
        $encoding = new Encoding(
            contentType: 'text/plain',
            allowReserved: true,
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('allowReserved', $serialized);
        self::assertTrue($serialized['allowReserved']);
    }

    #[Test]
    public function json_serialize_with_all_fields(): void
    {
        $nestedEncoding = new Encoding(contentType: 'text/plain');
        $headers = new Headers(['X-Test' => new Header(description: 'Test')]);

        $encoding = new Encoding(
            contentType: 'multipart/form-data',
            headers: $headers,
            style: 'form',
            explode: true,
            allowReserved: false,
            encoding: ['field1' => $nestedEncoding],
            prefixEncoding: [new Encoding(contentType: 'application/json')],
            itemEncoding: new Encoding(contentType: 'application/json'),
        );

        $serialized = $encoding->jsonSerialize();

        self::assertArrayHasKey('contentType', $serialized);
        self::assertArrayHasKey('headers', $serialized);
        self::assertArrayHasKey('style', $serialized);
        self::assertArrayHasKey('explode', $serialized);
        self::assertArrayHasKey('allowReserved', $serialized);
        self::assertArrayHasKey('encoding', $serialized);
        self::assertArrayHasKey('prefixEncoding', $serialized);
        self::assertArrayHasKey('itemEncoding', $serialized);
    }
}
