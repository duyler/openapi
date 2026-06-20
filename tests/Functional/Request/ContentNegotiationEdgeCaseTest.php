<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
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

    private const string WILDCARD_APPLICATION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Wildcard Application Media Type API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          application/*: {}
      responses:
        '201':
          description: Created
YAML;

    private const string WILDCARD_ANY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Wildcard Any Media Type API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          '*/*': {}
      responses:
        '201':
          description: Created
YAML;

    private const string WILDCARD_APPLICATION_WITH_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Wildcard Application With Schema API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          application/*:
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

    private const string EXACT_AND_APPLICATION_WILDCARD_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Exact And Application Wildcard Priority API
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
          application/*:
            schema:
              type: string
      responses:
        '201':
          description: Created
YAML;

    private const string APPLICATION_AND_ANY_WILDCARD_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Application And Any Wildcard Priority API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          application/*:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
          '*/*':
            schema:
              type: string
      responses:
        '201':
          description: Created
YAML;

    private const string MULTIPART_ARRAY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Array Schema API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: upload
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: array
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_OBJECT_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Object Schema API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: upload
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
      responses:
        '200':
          description: Uploaded
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

    #[Test]
    public function double_semicolon_in_content_type_treated_gracefully(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('application/json;;charset=utf-8');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function double_semicolon_with_unknown_media_type_throws_unsupported(): void
    {
        $validator = $this->buildValidator();
        $request = $this->createPostRequest('unknown/type;;charset=utf-8');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected UnsupportedMediaTypeException for unknown media type with double semicolon');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('unknown/type', $exception->mediaType);
            $this->assertSame(['application/json'], $exception->supportedTypes);
        }
    }

    #[Test]
    public function application_wildcard_spec_parses_application_json_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_APPLICATION_WITH_SCHEMA_SPEC)
            ->build();
        $request = $this->createPostRequest('application/json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function application_wildcard_matches_application_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_APPLICATION_SPEC)
            ->build();
        $request = $this->createPostRequest('application/json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function application_wildcard_matches_application_xml(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_APPLICATION_SPEC)
            ->build();
        $request = $this->factory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->factory->createStream('<name>sample</name>'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function any_wildcard_matches_application_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_ANY_SPEC)
            ->build();
        $request = $this->createPostRequest('application/json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function any_wildcard_matches_text_plain(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_ANY_SPEC)
            ->build();
        $request = $this->factory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->factory->createStream('plain text body'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function exact_match_takes_precedence_over_application_wildcard(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXACT_AND_APPLICATION_WILDCARD_SPEC)
            ->build();
        $request = $this->createPostRequest('application/json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function application_wildcard_takes_precedence_over_any_wildcard(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::APPLICATION_AND_ANY_WILDCARD_SPEC)
            ->build();
        $request = $this->createPostRequest('application/json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function text_plain_does_not_match_application_wildcard_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WILDCARD_APPLICATION_SPEC)
            ->build();
        $request = $this->createPostRequest('text/plain');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected UnsupportedMediaTypeException for text/plain against application/* spec');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('text/plain', $exception->mediaType);
            $this->assertSame(['application/*'], $exception->supportedTypes);
        }
    }

    #[Test]
    public function multipart_boundary_with_dot_dash_underscore_parses_correctly(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPART_ARRAY_SPEC)
            ->build();

        $boundary = 'X.Y-Z_123';
        $body = "boundary={$boundary}\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--{$boundary}--";

        $request = $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->factory->createStream($body));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/upload', $operation->path);
    }

    #[Test]
    public function multipart_parsed_body_is_list_not_object_against_object_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPART_OBJECT_SPEC)
            ->build();

        $boundary = 'X.Y-Z_123';
        $body = "boundary={$boundary}\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--{$boundary}--";

        $request = $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->factory->createStream($body));

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError because multipart parsed body is a list, not an object');
        } catch (TypeMismatchError $error) {
            $this->assertSame('object', $error->params()['expected']);
            $this->assertSame('array', $error->params()['actual']);
            $this->assertSame('type', $error->keyword());
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
