<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class OpenApiValidatorBuilderBodyLimitsTest extends TestCase
{
    private const string JSON_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: JSON Body Limit API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
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
      responses:
        '201':
          description: Created
YAML;

    private const string MULTIPART_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Body Limit API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              properties:
                file: {}
      responses:
        '201':
          description: Created
YAML;

    private const string STREAMING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Limit API
  version: 1.0.0
paths:
  /stream:
    get:
      operationId: streamRecords
      responses:
        '200':
          description: Streaming records
          content:
            application/jsonl:
              itemSchema:
                type: object
                required:
                  - id
                properties:
                  id:
                    type: integer
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function with_max_json_body_size_rejects_request_body_over_cap(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_BODY_SPEC)
            ->withMaxJsonBodySize(16)
            ->build();

        $request = $this->jsonRequest((string) json_encode(['name' => str_repeat('a', 32)], JSON_THROW_ON_ERROR));

        $this->expectException(BodyTooLargeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function with_max_json_body_size_accepts_request_body_under_cap(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_BODY_SPEC)
            ->withMaxJsonBodySize(1024)
            ->build();

        $operation = $validator->validateRequest($this->jsonRequest('{"name":"John"}'));

        $this->assertSame('/users', $operation->path);
    }

    #[Test]
    public function with_max_multipart_body_size_rejects_multipart_body_over_cap(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPART_BODY_SPEC)
            ->withMaxMultipartBodySize(48)
            ->build();

        $request = $this->multipartRequest('bx', str_repeat('a', 128));

        $this->expectException(BodyTooLargeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function with_max_multipart_body_size_accepts_multipart_body_under_cap(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPART_BODY_SPEC)
            ->withMaxMultipartBodySize(4096)
            ->build();

        $operation = $validator->validateRequest($this->multipartRequest('bx', 'binary data'));

        $this->assertSame('/upload', $operation->path);
    }

    #[Test]
    public function strict_streaming_defaults_to_fail_open_and_skips_malformed_record(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_SPEC)
            ->build();

        $validator->validateResponse($this->streamingResponse(), $this->streamOperation($validator));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function enable_strict_streaming_throws_on_malformed_record(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_SPEC)
            ->enableStrictStreaming()
            ->build();

        $this->expectException(MalformedStreamRecordException::class);
        $validator->validateResponse($this->streamingResponse(), $this->streamOperation($validator));
    }

    #[Test]
    public function disable_strict_streaming_restores_fail_open_after_enable(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_SPEC)
            ->enableStrictStreaming()
            ->disableStrictStreaming()
            ->build();

        $validator->validateResponse($this->streamingResponse(), $this->streamOperation($validator));

        $this->expectNotToPerformAssertions();
    }

    private function jsonRequest(string $body): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));
    }

    private function multipartRequest(string $boundary, string $content): ServerRequestInterface
    {
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"data.bin\"\r\n"
            . "Content-Type: application/octet-stream\r\n"
            . "\r\n"
            . $content . "\r\n"
            . "--{$boundary}--\r\n";

        return $this->psrFactory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->psrFactory->createStream($body));
    }

    private function streamingResponse(): ResponseInterface
    {
        $body = '{"id":1}' . "\n" . '{"broken' . "\n" . '{"id":3}';

        return $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));
    }

    private function streamOperation(OpenApiValidatorInterface $validator): Operation
    {
        $request = $this->psrFactory->createServerRequest('GET', '/stream');

        return $validator->validateRequest($request);
    }
}
