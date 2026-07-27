<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Parser\Internal\ComponentTreeBuilder;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentTreeBuilder::class)]
final class ComponentTreeBuilderTest extends TestCase
{
    private ComponentTreeBuilder $builder;
    private OpenApiBuildContext $context;

    protected function setUp(): void
    {
        $this->context = new OpenApiBuildContext();
        $this->builder = new ComponentTreeBuilder($this->context);
    }

    #[Test]
    public function build_request_body_minimal(): void
    {
        $body = $this->builder->buildRequestBody([]);

        self::assertNull($body->description);
        self::assertNull($body->content);
        self::assertFalse($body->required);
    }

    #[Test]
    public function build_request_body_with_content(): void
    {
        $body = $this->builder->buildRequestBody([
            'description' => 'payload',
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            'required' => true,
        ]);

        self::assertSame('payload', $body->description);
        self::assertInstanceOf(Content::class, $body->content);
        self::assertTrue($body->required);
    }

    #[Test]
    public function build_content_lowercases_media_type_keys(): void
    {
        $content = $this->builder->buildContent([
            'APPLICATION/JSON' => ['schema' => ['type' => 'object']],
        ]);

        self::assertArrayHasKey('application/json', $content->mediaTypes);
        self::assertInstanceOf(MediaType::class, $content->mediaTypes['application/json']);
    }

    #[Test]
    public function build_media_type_with_schema_and_examples(): void
    {
        $media = $this->builder->buildMediaType([
            'schema' => ['type' => 'object'],
            'examples' => ['first' => ['summary' => 'first example']],
        ]);

        self::assertNotNull($media->schema);
        self::assertSame('object', $media->schema->type);
        self::assertArrayHasKey('first', $media->examples ?? []);
    }

    #[Test]
    public function build_media_type_logs_example_deprecation_under_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->builder->buildMediaType([
            'schema' => ['type' => 'object'],
            'example' => ['foo' => 'bar'],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_encoding_with_nested_encoding_map(): void
    {
        $encoding = $this->builder->buildEncoding([
            'contentType' => 'application/json',
            'encoding' => [
                'nested' => ['contentType' => 'text/plain'],
            ],
        ]);

        self::assertSame('application/json', $encoding->contentType);
        self::assertArrayHasKey('nested', $encoding->encoding ?? []);
        self::assertInstanceOf(Encoding::class, $encoding->encoding['nested']);
    }

    #[Test]
    public function build_prefix_encoding_returns_indexed_list(): void
    {
        $encodings = $this->builder->buildPrefixEncoding([
            ['contentType' => 'application/json'],
            ['contentType' => 'text/plain'],
        ]);

        self::assertCount(2, $encodings);
        self::assertSame('application/json', $encodings[0]->contentType);
        self::assertSame('text/plain', $encodings[1]->contentType);
    }

    #[Test]
    public function build_example_with_all_fields(): void
    {
        $example = $this->builder->buildExample([
            'summary' => 'An example',
            'description' => 'Description',
            'value' => ['foo' => 'bar'],
            'externalValue' => 'https://example.com/example.json',
        ]);

        self::assertInstanceOf(Example::class, $example);
        self::assertSame('An example', $example->summary);
        self::assertSame(['foo' => 'bar'], $example->value);
        self::assertSame('https://example.com/example.json', $example->externalValue);
    }

    #[Test]
    public function build_headers_returns_map(): void
    {
        $headers = $this->builder->buildHeaders([
            'X-Rate-Limit' => ['description' => 'limit', 'schema' => ['type' => 'integer']],
        ]);

        self::assertInstanceOf(Headers::class, $headers);
        self::assertInstanceOf(Header::class, $headers->headers['X-Rate-Limit']);
    }

    #[Test]
    public function build_header_logs_allow_empty_value_deprecation_under_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->builder->buildHeader([
            'description' => 'header',
            'allowEmptyValue' => true,
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_content_or_null_returns_null_when_absent(): void
    {
        self::assertNull($this->builder->buildContentOrNull([]));
    }

    #[Test]
    public function build_request_body_or_null_returns_null_when_absent(): void
    {
        self::assertNull($this->builder->buildRequestBodyOrNull([]));
    }

    #[Test]
    public function build_headers_or_null_returns_null_when_absent(): void
    {
        self::assertNull($this->builder->buildHeadersOrNull([]));
    }
}
