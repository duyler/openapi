<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function assert;

final class OpenApiV32Test extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function parses_full_v3_2_spec(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        self::assertSame('3.2.0', $document->openapi);
        self::assertSame('https://api.example.com/openapi.json', $document->self);
        self::assertNotNull($document->servers);
        self::assertSame('production', $document->servers->servers[0]->name);
    }

    #[Test]
    public function validates_query_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/query-method.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('QUERY', '/search')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'query' => 'test',
                'filters' => ['active'],
            ])));

        $operation = $validator->validateRequest($request);

        self::assertSame('/search', $operation->path);
        self::assertSame('QUERY', $operation->method);
    }

    #[Test]
    public function validates_streaming_sse_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/streaming-events.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');
        $operation = $validator->validateRequest($request);

        $body = "event: message\ndata: hello world\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_jsonl_streaming(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/streaming-events.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = "{\"level\":\"info\",\"message\":\"Test\"}\n{\"level\":\"error\",\"message\":\"Error\"}";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_additional_operations(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/full-spec.yaml')
            ->build();

        $copyRequest = $this->psrFactory->createServerRequest('COPY', '/resource');
        $moveRequest = $this->psrFactory->createServerRequest('MOVE', '/resource');

        $copyOperation = $validator->validateRequest($copyRequest);
        $moveOperation = $validator->validateRequest($moveRequest);

        self::assertSame('COPY', $copyOperation->method);
        self::assertSame('MOVE', $moveOperation->method);
    }

    #[Test]
    public function backward_compatible_with_v3_1(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/petstore.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        self::assertSame('3.1.0', $document->openapi);
        self::assertNotNull($document->paths);
    }

    #[Test]
    public function backward_compatible_with_v3_0(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.0/simple-api.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        self::assertSame('3.0.3', $document->openapi);
        self::assertNotNull($document->paths);
    }

    #[Test]
    public function validates_querystring_parameter(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/full-spec.yaml')
            ->build();

        $filter = ['query' => 'test', 'limit' => 10];
        $encodedFilter = rawurlencode(json_encode($filter));

        $request = $this->psrFactory->createServerRequest('GET', '/search?' . $encodedFilter);
        $operation = $validator->validateRequest($request);

        self::assertSame('/search', $operation->path);
        self::assertSame('GET', $operation->method);
    }

    #[Test]
    public function parses_discriminator_with_default_mapping(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $schemas = $document->components?->schemas;
        $userSchema = $schemas['User'] ?? null;
        self::assertNotNull($userSchema);
        self::assertNotNull($userSchema->discriminator);
        self::assertSame('type', $userSchema->discriminator->propertyName);
        self::assertSame('#/components/schemas/User', $userSchema->discriminator->defaultMapping);
        self::assertSame(['admin' => '#/components/schemas/AdminUser'], $userSchema->discriminator->mapping);
    }

    #[Test]
    public function validates_response_with_discriminator_default_mapping(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/full-spec.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                ['id' => 1, 'name' => 'Test', 'type' => 'unknown'],
            ])));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function parses_server_name(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        self::assertNotNull($document->servers);
        self::assertCount(1, $document->servers->servers);
        self::assertSame('production', $document->servers->servers[0]->name);
        self::assertSame('https://api.example.com', $document->servers->servers[0]->url);
    }

    #[Test]
    public function parses_tags_with_parent_and_kind(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        self::assertNotNull($document->tags);
        self::assertCount(3, $document->tags->tags);

        $operationsTag = array_find($document->tags->tags, fn($tag) => $tag->name === 'Operations');

        self::assertNotNull($operationsTag);
        self::assertSame('Admin', $operationsTag->parent);
        self::assertNull($operationsTag->kind);

        $usersTag = array_find($document->tags->tags, fn($tag) => $tag->name === 'Users');

        self::assertNotNull($usersTag);
        self::assertSame('nav', $usersTag->kind);
    }

    #[Test]
    public function parses_oauth2_device_code_flow(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $securitySchemes = $document->components?->securitySchemes;
        $oauth2Scheme = $securitySchemes['oauth2'] ?? null;
        self::assertNotNull($oauth2Scheme);
        self::assertSame('https://auth.example.com/.well-known/oauth-authorization-server', $oauth2Scheme->oauth2MetadataUrl);

        $deviceCodeFlow = $oauth2Scheme->flows?->deviceCode;
        self::assertNotNull($deviceCodeFlow);
        self::assertSame('https://auth.example.com/token', $deviceCodeFlow->tokenUrl);
        self::assertSame('https://auth.example.com/device/code', $deviceCodeFlow->deviceAuthorizationUrl);
    }

    #[Test]
    public function parses_media_types_component(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $mediaTypes = $document->components?->mediaTypes;
        self::assertNotNull($mediaTypes);
        self::assertArrayHasKey('ProblemJson', $mediaTypes);
    }

    #[Test]
    public function parses_path_item_summary(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $usersPath = $document->paths?->paths['/users'] ?? null;
        self::assertNotNull($usersPath);
        self::assertSame('User collection', $usersPath->summary);
    }

    #[Test]
    public function parses_response_summary(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $usersPath = $document->paths?->paths['/users'] ?? null;
        self::assertNotNull($usersPath);
        $getResponse = $usersPath->get?->responses?->responses['200'] ?? null;
        self::assertNotNull($getResponse);
        self::assertSame('Successful response', $getResponse->summary);
    }
}
