<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

final class StreamingResponseValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function jsonl_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"Test message"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:01Z","level":"error","message":"Error occurred"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function jsonl_with_charset_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"debug","message":"Debug message"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl; charset=utf-8')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ndjson_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/ndjson');
        $operation = $validator->validateRequest($request);

        $body = '{"name":"item1","count":10}' . "\n"
            . '{"name":"item2","count":20}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-ndjson')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sse_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');
        $operation = $validator->validateRequest($request);

        $body = "event: message\n"
            . "data: {\"message\":\"hello\",\"count\":1}\n\n"
            . "event: update\n"
            . "data: {\"message\":\"world\",\"count\":2}\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sse_with_id_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');
        $operation = $validator->validateRequest($request);

        $body = "id: 123\n"
            . "event: message\n"
            . "data: {\"message\":\"test\",\"count\":1}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sse_ignores_comments(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');
        $operation = $validator->validateRequest($request);

        $body = ": this is a comment\n"
            . "event: message\n"
            . "data: {\"message\":\"hello\",\"count\":1}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function json_seq_valid_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/records');
        $operation = $validator->validateRequest($request);

        $body = "\x1E" . '{"id":"1","value":"first"}' . "\x1E" . '{"id":"2","value":"second"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json-seq')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function jsonl_empty_lines_ignored(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"Test"}' . "\n\n"
            . '{"timestamp":"2024-01-01T00:00:01Z","level":"warn","message":"Warning"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function streaming_uses_schema_when_item_schema_not_defined(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/stream-with-schema');
        $operation = $validator->validateRequest($request);

        $body = '{"fallback":true}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_streaming_content_type_unchanged(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /regular:
    get:
      responses:
        '200':
          description: Regular JSON response
          content:
            application/json:
              schema:
                type: object
                properties:
                  message:
                    type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/regular');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'message' => 'Hello',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
