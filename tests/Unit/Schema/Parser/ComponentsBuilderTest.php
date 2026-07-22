<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Parser\ComponentsBuilder;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComponentsBuilderTest extends TestCase
{
    private ComponentsBuilder $componentsBuilder;
    private OpenApiBuildContext $context;

    protected function setUp(): void
    {
        $this->context = new OpenApiBuildContext();
        $this->componentsBuilder = $this->context->componentsBuilder;
    }

    #[Test]
    public function build_request_body_with_content_and_required(): void
    {
        $body = $this->componentsBuilder->buildRequestBody([
            'description' => 'user payload',
            'content' => [
                'application/json' => ['schema' => ['type' => 'object']],
            ],
            'required' => true,
        ]);

        self::assertInstanceOf(RequestBody::class, $body);
        self::assertSame('user payload', $body->description);
        self::assertTrue($body->required);
        self::assertInstanceOf(Content::class, $body->content);
    }

    #[Test]
    public function build_request_body_minimal(): void
    {
        $body = $this->componentsBuilder->buildRequestBody([]);

        self::assertNull($body->description);
        self::assertNull($body->content);
        self::assertFalse($body->required);
    }

    #[Test]
    public function build_content_lowercases_media_type_keys(): void
    {
        $content = $this->componentsBuilder->buildContent([
            'APPLICATION/JSON' => ['schema' => ['type' => 'object']],
        ]);

        self::assertArrayHasKey('application/json', $content->mediaTypes);
        self::assertInstanceOf(MediaType::class, $content->mediaTypes['application/json']);
    }

    #[Test]
    public function build_media_type_with_schema_and_examples(): void
    {
        $media = $this->componentsBuilder->buildMediaType([
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

        $this->componentsBuilder->buildMediaType([
            'schema' => ['type' => 'object'],
            'example' => ['foo' => 'bar'],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_responses_returns_status_code_map(): void
    {
        $responses = $this->componentsBuilder->buildResponses([
            '200' => ['description' => 'OK'],
            '404' => ['description' => 'Not Found'],
        ]);

        self::assertInstanceOf(Responses::class, $responses);
        self::assertInstanceOf(Response::class, $responses->responses['200']);
        self::assertSame('Not Found', $responses->responses['404']->description);
    }

    #[Test]
    public function build_response_by_ref(): void
    {
        $response = $this->componentsBuilder->buildResponse([
            '$ref' => '#/components/responses/NotFound',
            'summary' => 'Ref summary',
            'description' => 'Ref description',
        ]);

        self::assertSame('#/components/responses/NotFound', $response->ref);
        self::assertSame('Ref summary', $response->refSummary);
        self::assertSame('Ref description', $response->refDescription);
    }

    #[Test]
    public function build_headers_returns_map(): void
    {
        $headers = $this->componentsBuilder->buildHeaders([
            'X-Rate-Limit' => ['description' => 'limit', 'schema' => ['type' => 'integer']],
        ]);

        self::assertInstanceOf(Headers::class, $headers);
        self::assertInstanceOf(Header::class, $headers->headers['X-Rate-Limit']);
    }

    #[Test]
    public function build_header_logs_allow_empty_value_deprecation_under_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->componentsBuilder->buildHeader([
            'description' => 'header',
            'allowEmptyValue' => true,
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_encoding_with_nested_encoding_map(): void
    {
        $encoding = $this->componentsBuilder->buildEncoding([
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
        $encodings = $this->componentsBuilder->buildPrefixEncoding([
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
        $example = $this->componentsBuilder->buildExample([
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
    public function build_components_aggregates_all_component_maps(): void
    {
        $components = $this->componentsBuilder->buildComponents([
            'schemas' => ['User' => ['type' => 'object']],
            'responses' => ['NotFound' => ['description' => 'Not Found']],
            'parameters' => ['limitParam' => ['name' => 'limit', 'in' => 'query']],
            'examples' => ['example1' => ['summary' => 'ex']],
            'requestBodies' => ['UserBody' => ['required' => true]],
            'headers' => ['X-Trace' => ['description' => 'trace']],
            'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
            'links' => ['UserLink' => ['operationId' => 'getUser']],
            'callbacks' => [
                'myCallback' => [
                    '{$request.body#/callback_url}' => [
                        'post' => ['responses' => ['200' => ['description' => 'OK']]],
                    ],
                ],
            ],
            'pathItems' => [
                'UserPath' => ['get' => ['responses' => ['200' => ['description' => 'OK']]]],
            ],
            'mediaTypes' => [
                'application/json' => ['schema' => ['type' => 'object']],
            ],
        ]);

        self::assertInstanceOf(Components::class, $components);
        self::assertArrayHasKey('User', $components->schemas ?? []);
        self::assertArrayHasKey('NotFound', $components->responses ?? []);
        self::assertArrayHasKey('limitParam', $components->parameters ?? []);
        self::assertArrayHasKey('example1', $components->examples ?? []);
        self::assertArrayHasKey('UserBody', $components->requestBodies ?? []);
        self::assertArrayHasKey('X-Trace', $components->headers ?? []);
        self::assertArrayHasKey('bearerAuth', $components->securitySchemes ?? []);
        self::assertArrayHasKey('UserLink', $components->links ?? []);
        self::assertArrayHasKey('myCallback', $components->callbacks ?? []);
        self::assertArrayHasKey('UserPath', $components->pathItems ?? []);
        self::assertArrayHasKey('application/json', $components->mediaTypes ?? []);
    }

    #[Test]
    public function build_components_with_empty_data_returns_empty_components(): void
    {
        $components = $this->componentsBuilder->buildComponents([]);

        self::assertInstanceOf(Components::class, $components);
        self::assertNull($components->schemas);
        self::assertNull($components->responses);
        self::assertNull($components->parameters);
    }

    #[Test]
    public function build_callbacks_components_returns_per_name_callbacks(): void
    {
        $callbacks = $this->componentsBuilder->buildCallbacksComponents([
            'cb1' => [
                'http://example.com' => [
                    'post' => ['responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ]);

        self::assertArrayHasKey('cb1', $callbacks);
    }

    #[Test]
    public function build_link_with_operation_id(): void
    {
        $link = $this->componentsBuilder->buildLink([
            'operationId' => 'getUserById',
            'parameters' => ['id' => '$response.body#/id'],
            'description' => 'link to user',
        ]);

        self::assertSame('getUserById', $link->operationId);
        self::assertSame('link to user', $link->description);
        self::assertSame(['id' => '$response.body#/id'], $link->parameters);
    }

    #[Test]
    public function build_media_type_with_streaming_3_2_fields(): void
    {
        $media = $this->componentsBuilder->buildMediaType([
            'itemSchema' => ['type' => 'object'],
            'encoding' => ['prop' => ['contentType' => 'text/plain']],
            'itemEncoding' => ['contentType' => 'application/json'],
            'prefixEncoding' => [['contentType' => 'application/json']],
        ]);

        self::assertNotNull($media->itemSchema);
        self::assertSame('object', $media->itemSchema->type);
        self::assertArrayHasKey('prop', $media->encoding ?? []);
        self::assertInstanceOf(Encoding::class, $media->itemEncoding);
        self::assertSame('application/json', $media->itemEncoding->contentType);
        self::assertCount(1, $media->prefixEncoding ?? []);
        self::assertSame('application/json', $media->prefixEncoding[0]->contentType);
    }

    #[Test]
    public function build_response_full_object_without_ref(): void
    {
        $response = $this->componentsBuilder->buildResponse([
            'description' => 'OK',
            'headers' => ['X-Trace' => ['description' => 'trace']],
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            'links' => ['UserLink' => ['operationId' => 'getUser']],
        ]);

        self::assertNull($response->ref);
        self::assertSame('OK', $response->description);
        self::assertInstanceOf(Headers::class, $response->headers);
        self::assertInstanceOf(Content::class, $response->content);
        self::assertInstanceOf(Links::class, $response->links);
    }

    #[Test]
    public function build_link_with_ref_and_server(): void
    {
        $link = $this->componentsBuilder->buildLink([
            '$ref' => '#/components/links/UserLink',
            'operationRef' => '#/operations/getUser',
            'description' => 'cross-ref link',
            'server' => ['url' => 'https://api.example.com'],
        ]);

        self::assertSame('#/components/links/UserLink', $link->ref);
        self::assertSame('#/operations/getUser', $link->operationRef);
        self::assertSame('cross-ref link', $link->description);
        self::assertNotNull($link->server);
        self::assertSame('https://api.example.com', $link->server->url);
    }
}
