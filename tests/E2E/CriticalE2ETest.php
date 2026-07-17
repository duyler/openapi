<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use JsonException;
use Override;

use function sprintf;

final class CriticalE2ETest extends TestCase
{
    private const string PETSTORE_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: E2E Test API
  version: 1.0.0
paths:
  /pets:
    get:
      description: List all pets
      responses:
        '200':
          description: A list of pets
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  required:
                    - id
                    - name
                  properties:
                    id:
                      type: integer
                      format: int64
                    name:
                      type: string
                    tag:
                      type: string
    post:
      description: Create a pet
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
                tag:
                  type: string
      responses:
        '201':
          description: Pet created
          content:
            application/json:
              schema:
                type: object
                required:
                  - id
                  - name
                properties:
                  id:
                    type: integer
                  name:
                    type: string
  /pets/{petId}:
    get:
      description: Get pet by ID
      parameters:
        - name: petId
          in: path
          required: true
          schema:
            type: string
            pattern: '/^[0-9]+$/'
      responses:
        '200':
          description: A pet
          content:
            application/json:
              schema:
                type: object
                required:
                  - id
                  - name
                properties:
                  id:
                    type: integer
                  name:
                    type: string
                  tag:
                    type: string
YAML;
    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function full_request_response_cycle_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/pets')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'Fido',
                'tag' => 'dog',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/pets', $operation->path);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'Fido',
            ])));

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function malformed_json_body_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $malformedJsonStrings = [
            '{invalid json',
            '{"key": }',
            '[1, 2, 3,]',
        ];

        foreach ($malformedJsonStrings as $malformedJson) {
            $request = $this->psrFactory->createServerRequest('POST', '/pets')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->psrFactory->createStream($malformedJson));

            $caught = false;
            try {
                $validator->validateRequest($request);
            } catch (JsonException|ValidationException|BuilderException) {
                $caught = true;
            }

            $this->assertTrue($caught, sprintf('Expected exception for malformed JSON: %s', $malformedJson));
        }
    }

    #[Test]
    public function missing_required_request_body_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/pets')
            ->withHeader('Content-Type', 'application/json');

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function unknown_path_throws_operation_not_found(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/unknown-path');

        $this->expectException(OperationNotFoundException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function wrong_http_method_throws_operation_not_found(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('DELETE', '/pets');

        $this->expectException(OperationNotFoundException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function wrong_response_content_type_skips_body_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/pets')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'Fido',
            ])));

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'text/xml')
            ->withBody($this->psrFactory->createStream('<pet><id>1</id><name>Fido</name></pet>'));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function multipart_form_data_request_parses_and_validates(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/request-validation-specs/multipart-data.yaml')
            ->build();

        $multipartBody = "boundary=----TestBoundary12345\r\n\r\n"
            . "------TestBoundary12345\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"test.txt\"\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "file content here\r\n"
            . "------TestBoundary12345\r\n"
            . "Content-Disposition: form-data; name=\"name\"\r\n"
            . "\r\n"
            . "Test Upload\r\n"
            . "------TestBoundary12345\r\n"
            . "Content-Disposition: form-data; name=\"category\"\r\n"
            . "\r\n"
            . "document\r\n"
            . "------TestBoundary12345--\r\n";

        $request = $this->psrFactory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=----TestBoundary12345')
            ->withBody($this->psrFactory->createStream($multipartBody));

        $exceptionCaught = false;
        $exceptionMessage = '';
        try {
            $validator->validateRequest($request);
        } catch (ValidationException $e) {
            $exceptionCaught = true;
            $exceptionMessage = $e->getMessage();
        }

        $this->assertTrue($exceptionCaught, 'Multipart data parsed as array triggers schema validation');
        $this->assertStringContainsString('file', $exceptionMessage);
    }
}
