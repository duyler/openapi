<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Request\Internal\PartContentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartContentType::class)]
final class PartContentTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('defaultContentTypeTable')]
    public function default_content_type_follows_the_specification_table(?Schema $schema, string $expected): void
    {
        self::assertSame($expected, PartContentType::defaultForSchema($schema));
    }

    public static function defaultContentTypeTable(): iterable
    {
        yield 'absent type' => [
            'schema' => new Schema(),
            'expected' => 'application/octet-stream',
        ];

        yield 'null schema' => [
            'schema' => null,
            'expected' => 'application/octet-stream',
        ];

        yield 'string without contentEncoding' => [
            'schema' => new Schema(type: 'string'),
            'expected' => 'text/plain',
        ];

        yield 'string with contentEncoding' => [
            'schema' => new Schema(type: 'string', contentEncoding: 'binary'),
            'expected' => 'application/octet-stream',
        ];

        yield 'string with non-binary contentEncoding' => [
            'schema' => new Schema(type: 'string', contentEncoding: 'base64'),
            'expected' => 'application/octet-stream',
        ];

        yield 'number' => [
            'schema' => new Schema(type: 'number'),
            'expected' => 'text/plain',
        ];

        yield 'integer' => [
            'schema' => new Schema(type: 'integer'),
            'expected' => 'text/plain',
        ];

        yield 'boolean' => [
            'schema' => new Schema(type: 'boolean'),
            'expected' => 'text/plain',
        ];

        yield 'object' => [
            'schema' => new Schema(type: 'object'),
            'expected' => 'application/json',
        ];

        yield 'array with items' => [
            'schema' => new Schema(type: 'array', items: new Schema(type: 'string')),
            'expected' => 'application/json',
        ];

        yield 'array without items' => [
            'schema' => new Schema(type: 'array'),
            'expected' => 'application/json',
        ];

        yield 'union type prefers the non-null entry' => [
            'schema' => new Schema(type: ['string', 'null']),
            'expected' => 'text/plain',
        ];

        yield 'union type with null only' => [
            'schema' => new Schema(type: ['null']),
            'expected' => 'application/octet-stream',
        ];
    }

    #[Test]
    public function effective_prefers_the_part_header(): void
    {
        $encoding = new Encoding(contentType: 'application/xml');

        $effective = PartContentType::effective(
            headerValue: 'image/png; charset=utf-8',
            encoding: $encoding,
            schema: new Schema(type: 'object'),
        );

        self::assertSame('image/png', $effective);
    }

    #[Test]
    public function effective_falls_back_to_the_primary_encoding_content_type(): void
    {
        $encoding = new Encoding(contentType: ['application/xml', 'text/xml']);

        $effective = PartContentType::effective(
            headerValue: null,
            encoding: $encoding,
            schema: new Schema(type: 'object'),
        );

        self::assertSame('application/xml', $effective);
    }

    #[Test]
    public function effective_falls_back_to_the_schema_default(): void
    {
        $effective = PartContentType::effective(
            headerValue: null,
            encoding: new Encoding(),
            schema: new Schema(type: 'string'),
        );

        self::assertSame('text/plain', $effective);
    }

    #[Test]
    public function effective_normalizes_case_and_strips_parameters(): void
    {
        $effective = PartContentType::effective(
            headerValue: 'Application/JSON; charset=utf-8',
            encoding: null,
            schema: null,
        );

        self::assertSame('application/json', $effective);
    }

    #[Test]
    public function is_json_accepts_json_and_structured_suffixes(): void
    {
        self::assertTrue(PartContentType::isJson('application/json'));
        self::assertTrue(PartContentType::isJson('application/vnd.api+json'));
        self::assertFalse(PartContentType::isJson('text/plain'));
    }
}
