<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\Internal\EncodingHeaderRefResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncodingHeaderRefResolver::class)]
final class EncodingHeaderRefResolverTest extends TestCase
{
    #[Test]
    public function media_type_without_refs_is_returned_unchanged(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'text/html')]);
        $document = $this->document();

        self::assertSame($mediaType, $resolver->resolve($mediaType, $document));
    }

    #[Test]
    public function local_header_ref_is_resolved_from_components(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $mediaType = new MediaType(
            prefixEncoding: [
                new Encoding(
                    contentType: 'text/html',
                    headers: new Headers([
                        'Content-Location' => new Header(ref: '#/components/headers/ContentLocation'),
                    ]),
                ),
            ],
        );

        $resolved = $resolver->resolve($mediaType, $this->document());
        $header = $resolved->prefixEncoding[0]->headers->headers['Content-Location'];

        self::assertNull($header->ref);
        self::assertTrue($header->required);
        self::assertNotNull($header->schema);
    }

    #[Test]
    public function item_encoding_and_nested_encodings_are_resolved(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $mediaType = new MediaType(
            itemEncoding: new Encoding(
                headers: new Headers(['Content-Location' => new Header(ref: '#/components/headers/ContentLocation')]),
                prefixEncoding: [
                    new Encoding(
                        headers: new Headers(
                            ['Content-ID' => new Header(ref: '#/components/headers/NoContentId')],
                        ),
                    ),
                ],
            ),
        );

        $resolved = $resolver->resolve($mediaType, $this->document());

        self::assertTrue($resolved->itemEncoding->headers->headers['Content-Location']->required);
        self::assertFalse($resolved->itemEncoding->prefixEncoding[0]->headers->headers['Content-ID']->required);
    }

    #[Test]
    public function named_encoding_map_is_resolved(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $mediaType = new MediaType(
            encoding: [
                'doc' => new Encoding(
                    headers: new Headers(['Content-Location' => new Header(ref: '#/components/headers/ContentLocation')]),
                ),
            ],
        );

        $resolved = $resolver->resolve($mediaType, $this->document());

        self::assertTrue($resolved->encoding['doc']->headers->headers['Content-Location']->required);
    }

    #[Test]
    public function unresolved_refs_are_kept_as_is(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $stub = new Header(ref: '#/components/headers/Missing');
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(headers: new Headers(['X-Missing' => $stub]))],
        );

        $resolved = $resolver->resolve($mediaType, $this->document());

        self::assertSame($stub, $resolved->prefixEncoding[0]->headers->headers['X-Missing']);
    }

    #[Test]
    public function external_refs_are_kept_as_is(): void
    {
        $resolver = new EncodingHeaderRefResolver();
        $stub = new Header(ref: 'https://example.com/headers.yaml#/ContentLocation');
        $mediaType = new MediaType(
            itemEncoding: new Encoding(headers: new Headers(['Content-Location' => $stub])),
        );

        $resolved = $resolver->resolve($mediaType, $this->document());

        self::assertSame($stub, $resolved->itemEncoding->headers->headers['Content-Location']);
    }

    private function document(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Ref Resolution', version: '1.0.0'),
            components: new Components(
                headers: [
                    'ContentLocation' => new Header(
                        required: true,
                        schema: new Schema(type: 'string'),
                    ),
                    'NoContentId' => new Header(description: 'Use Content-Location instead of Content-ID'),
                ],
            ),
        );
    }
}
