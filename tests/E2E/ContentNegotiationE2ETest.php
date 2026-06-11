<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

final class ContentNegotiationE2ETest extends TestCase
{
    private const MULTI_CONTENT_TYPE_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Multi Content-Type API
  version: 1.0.0
paths:
  /data:
    post:
      summary: Accept JSON or XML
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
                value:
                  type: string
          application/xml:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
                value:
                  type: string
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  name:
                    type: string
            application/xml:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
  /data/{id}:
    get:
      summary: Get data with multiple response types
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Data found
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  name:
                    type: string
            application/xml:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
YAML;

    private const NDJSON_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: NDJSON Streaming API
  version: 1.0.0
paths:
  /events:
    get:
      summary: Stream events as NDJSON
      responses:
        '200':
          description: Event stream
          content:
            application/x-ndjson:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  type:
                    type: string
                  payload:
                    type: string
YAML;

    private const SSE_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: SSE Streaming API
  version: 1.0.0
paths:
  /notifications:
    get:
      summary: Stream notifications via SSE
      responses:
        '200':
          description: Notification stream
          content:
            text/event-stream:
              schema:
                type: object
                properties:
                  event:
                    type: string
                  data:
                    type: object
                    properties:
                      message:
                        type: string
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function json_request_validates_against_multi_content_type_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'test',
                'value' => 'hello',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function xml_request_validates_against_multi_content_type_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $xmlBody = '<root><name>test</name><value>hello</value></root>';

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xmlBody));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function json_response_validates_against_multi_content_type_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'test',
            ])));

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'test',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function xml_response_validates_against_multi_content_type_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'test',
            ])));

        $operation = $validator->validateRequest($request);

        $xmlResponse = '<?xml version="1.0"?><root><id>1</id><name>test</name></root>';

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xmlResponse));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function full_xml_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $xmlBody = '<root><name>XML Item</name><value>xml-value</value></root>';

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xmlBody));

        $operation = $validator->validateRequest($request);

        $xmlResponse = '<?xml version="1.0"?><root><id>42</id><name>XML Item</name></root>';

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xmlResponse));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function get_with_json_accept_header_validates_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data/123')
            ->withHeader('Accept', 'application/json');

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 123,
                'name' => 'Item 123',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ndjson_streaming_response_validates_each_chunk(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NDJSON_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');

        $operation = $validator->validateRequest($request);

        $ndjsonBody = implode("\n", [
            json_encode(['id' => 1, 'type' => 'click', 'payload' => 'button_a']),
            json_encode(['id' => 2, 'type' => 'scroll', 'payload' => 'page_down']),
            json_encode(['id' => 3, 'type' => 'submit', 'payload' => 'form_login']),
        ]);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-ndjson')
            ->withBody($this->psrFactory->createStream($ndjsonBody));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ndjson_streaming_with_invalid_chunk_throws_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NDJSON_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');

        $operation = $validator->validateRequest($request);

        $ndjsonBody = implode("\n", [
            json_encode(['id' => 1, 'type' => 'click', 'payload' => 'ok']),
            json_encode(['id' => 'not_an_integer', 'type' => 'bad', 'payload' => 'fail']),
        ]);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-ndjson')
            ->withBody($this->psrFactory->createStream($ndjsonBody));

        $this->expectException(TypeMismatchError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function sse_streaming_response_validates_each_event(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SSE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/notifications');

        $operation = $validator->validateRequest($request);

        $sseBody = "event: notification\ndata: {\"message\":\"You have mail\"}\n\n"
            . "event: notification\ndata: {\"message\":\"System update\"}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($sseBody));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sse_streaming_with_invalid_event_data_throws_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SSE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/notifications');

        $operation = $validator->validateRequest($request);

        $sseBody = "event: notification\ndata: {\"message\":123}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($sseBody));

        $this->expectException(TypeMismatchError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function sse_with_comment_lines_parses_correctly(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SSE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/notifications');

        $operation = $validator->validateRequest($request);

        $sseBody = "event: notification\ndata: {\"message\":\"ping\"}\n\n"
            . ": this is a comment and should be ignored\n"
            . "event: notification\ndata: {\"message\":\"pong\"}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($sseBody));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
