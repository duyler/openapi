<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function json_encode;

final class ContentNegotiationEdgeCaseTest extends TestCase
{
    private const string JSON_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Content Negotiation Edge Case API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
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

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function accepts_application_json_with_charset_suffix(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('application/json; charset=utf-8');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function accepts_application_json_with_trailing_semicolon(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('application/json;');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function unsupported_media_type_exception_carries_empty_media_type_for_parameter_only_header(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('; charset=utf-8');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected UnsupportedMediaTypeException for Content-Type without media type');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('', $exception->mediaType);
            $this->assertSame(['application/json'], $exception->supportedTypes);
        }
    }

    #[Test]
    public function unsupported_media_type_exception_carries_empty_media_type_for_empty_header(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected UnsupportedMediaTypeException for empty Content-Type');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('', $exception->mediaType);
            $this->assertSame(['application/json'], $exception->supportedTypes);
        }
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_BODY_SPEC)
            ->build();
    }

    private function createPostRequest(string $contentType): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', $contentType)
            ->withBody(
                $this->factory->createStream((string) json_encode(['name' => 'sample'])),
            );
    }
}
