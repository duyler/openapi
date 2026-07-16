<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use JsonException;

/**
 * RB-08: vendor-specific media type (application/vnd.api+json) — JSON:API body
 * parsing and content negotiation.
 *
 * RB-09: optional request body (required: false) — verifies the validator does
 * not raise MissingRequestBodyException when the spec marks the body as optional
 * and the client sends an empty body.
 */
final class RequestBodyMediaTypeTest extends TestCase
{
    private const string VENDOR_MEDIA_TYPE_STRING_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Vendor Media Type String Schema API
  version: 1.0.0
paths:
  /resource:
    post:
      operationId: createResource
      requestBody:
        required: true
        content:
          application/vnd.api+json:
            schema:
              type: string
      responses:
        '201':
          description: Created
YAML;

    private const string VENDOR_MEDIA_TYPE_OBJECT_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Vendor Media Type Object Schema API
  version: 1.0.0
paths:
  /resource:
    post:
      operationId: createResource
      requestBody:
        required: true
        content:
          application/vnd.api+json:
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

    private const string VENDOR_AND_STANDARD_MEDIA_TYPE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Vendor And Standard Media Type API
  version: 1.0.0
paths:
  /resource:
    post:
      operationId: createResource
      requestBody:
        required: true
        content:
          application/vnd.api+json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
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

    private const string OPTIONAL_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Optional Body API
  version: 1.0.0
paths:
  /optional:
    post:
      operationId: createOptional
      requestBody:
        required: false
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

    private const string REQUIRED_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Required Body API
  version: 1.0.0
paths:
  /required:
    post:
      operationId: createRequired
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
    public function vendor_media_type_with_string_schema_rejects_json_object_as_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_MEDIA_TYPE_STRING_SCHEMA_SPEC)
            ->build();

        $request = $this->createVendorRequest('{"data":"payload"}');

        $thrown = null;

        try {
            $validator->validateRequest($request);
        } catch (TypeMismatchError $error) {
            $thrown = $error;
        }

        self::assertNotNull(
            $thrown,
            'Vendor media type body must be parsed as JSON; an object payload must raise '
            . 'TypeMismatchError against a string schema.',
        );
        self::assertSame('string', $thrown->params()['expected']);
        self::assertContains(
            $thrown->params()['actual'],
            ['array', 'object'],
            'Parsed JSON object must be reported as array or object, not raw string.',
        );
    }

    #[Test]
    public function vendor_media_type_with_object_schema_accepts_valid_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_MEDIA_TYPE_OBJECT_SCHEMA_SPEC)
            ->build();

        $request = $this->createVendorRequest('{"name":"John Doe"}');

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/resource');
    }

    #[Test]
    public function vendor_media_type_with_object_schema_rejects_malformed_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_MEDIA_TYPE_OBJECT_SCHEMA_SPEC)
            ->build();

        $request = $this->createVendorRequest('not a json object at all');

        $this->expectException(JsonException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function vendor_media_type_not_listed_in_spec_rejected_with_unsupported_media_type(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_MEDIA_TYPE_STRING_SCHEMA_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"John Doe"}'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function vendor_media_type_exact_match_routes_to_vendor_schema_not_standard_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_AND_STANDARD_MEDIA_TYPE_SPEC)
            ->build();

        $request = $this->createVendorRequest('{"name":"John Doe"}');

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/resource');
    }

    #[Test]
    public function standard_json_exact_match_parses_body_as_json(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_AND_STANDARD_MEDIA_TYPE_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/resource');
    }

    #[Test]
    public function optional_body_with_empty_body_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPTIONAL_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/optional')
            ->withBody($this->factory->createStream(''));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/optional');
    }

    #[Test]
    public function optional_body_with_whitespace_only_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPTIONAL_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/optional')
            ->withBody($this->factory->createStream('   '));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/optional');
    }

    #[Test]
    public function optional_body_without_content_type_with_empty_body_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPTIONAL_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/optional')
            ->withBody($this->factory->createStream(''));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/optional');
    }

    #[Test]
    public function optional_body_with_valid_payload_passes_full_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPTIONAL_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/optional')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/optional');
    }

    #[Test]
    public function optional_body_with_payload_missing_required_property_still_validates_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPTIONAL_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/optional')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"unknownField":"John Doe"}'));

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ValidationException $exception) {
            $caught = $exception;
        }

        self::assertNotNull(
            $caught,
            'Optional body with a non-empty payload must still undergo schema validation when a payload is present.',
        );

        $hasRequiredError = array_any(
            $caught->getErrors(),
            static fn($error): bool => $error instanceof RequiredError && 'name' === $error->params()['property'],
        );

        self::assertTrue(
            $hasRequiredError,
            'Expected RequiredError for missing "name" property when optional body carries a payload.',
        );
    }

    #[Test]
    public function required_body_with_empty_body_throws_missing_request_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/required')
            ->withBody($this->factory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function required_body_with_whitespace_only_throws_missing_request_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/required')
            ->withBody($this->factory->createStream("\n\t "));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function required_body_without_content_type_with_empty_body_throws_missing_request_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/required')
            ->withBody($this->factory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function required_body_with_valid_payload_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/required')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/required');
    }

    private function createVendorRequest(string $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/resource')
            ->withHeader('Content-Type', 'application/vnd.api+json')
            ->withBody($this->factory->createStream($body));
    }

    // Helper duplicated in MultipartUploadTest for test isolation.
    private function assertOperationMatches(Operation $operation, string $method, string $path): void
    {
        self::assertSame($method, $operation->method);
        self::assertSame($path, $operation->path);
    }
}
