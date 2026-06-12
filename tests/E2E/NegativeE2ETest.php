<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

final class NegativeE2ETest extends TestCase
{
    private const QUERY_PARAMS_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Negative Test API
  version: 1.0.0
paths:
  /search:
    get:
      summary: Search endpoint
      parameters:
        - name: page
          in: query
          required: true
          schema:
            type: integer
            minimum: 1
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 100
        - name: status
          in: query
          schema:
            type: string
            enum: [active, inactive, pending]
      responses:
        '200':
          description: Search results
YAML;

    private const HEADERS_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers Test API
  version: 1.0.0
paths:
  /secure-data:
    get:
      summary: Get secure data
      parameters:
        - name: Authorization
          in: header
          required: true
          schema:
            type: string
            minLength: 10
        - name: X-Request-ID
          in: header
          schema:
            type: string
            format: uuid
        - name: Accept
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Secure data
YAML;

    private const BODY_LIMITS_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Body Limits Test API
  version: 1.0.0
paths:
  /submit:
    post:
      summary: Submit data
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - title
                - description
              properties:
                title:
                  type: string
                  minLength: 1
                  maxLength: 100
                description:
                  type: string
                  maxLength: 500
                tags:
                  type: array
                  maxItems: 10
                  items:
                    type: string
                metadata:
                  type: object
                  maxProperties: 5
                  additionalProperties:
                    type: string
              additionalProperties: false
      responses:
        '201':
          description: Created
YAML;

    private const RESPONSE_VALIDATION_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Response Validation API
  version: 1.0.0
paths:
  /users/{userId}:
    get:
      summary: Get user
      parameters:
        - name: userId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: User data
          content:
            application/json:
              schema:
                type: object
                required:
                  - id
                  - name
                  - email
                properties:
                  id:
                    type: integer
                  name:
                    type: string
                  email:
                    type: string
                    format: email
                  age:
                    type: integer
                    minimum: 0
                  tags:
                    type: array
                    items:
                      type: string
                additionalProperties: false
YAML;

    private const CONTENT_TYPE_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Content Type Test API
  version: 1.0.0
paths:
  /data:
    post:
      summary: Post data
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                value:
                  type: string
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function invalid_query_parameter_type_string_instead_of_integer(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PARAMS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?page=not_a_number');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function missing_required_query_parameter_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PARAMS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?limit=10');

        $this->expectException(MissingParameterException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function invalid_query_parameter_enum_value_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PARAMS_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?page=1&status=unknown');

        $this->expectException(EnumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function query_parameter_integer_below_minimum_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PARAMS_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?page=0');

        $this->expectException(AbstractValidationError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function query_parameter_integer_above_maximum_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PARAMS_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?page=1&limit=101');

        $this->expectException(AbstractValidationError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function missing_required_header_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADERS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/secure-data')
            ->withHeader('Accept', 'application/json');

        $this->expectException(MissingParameterException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function required_header_with_invalid_format_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADERS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/secure-data')
            ->withHeader('Authorization', 'Bearer valid-token')
            ->withHeader('X-Request-ID', 'not-a-uuid')
            ->withHeader('Accept', 'application/json');

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_value_too_short_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADERS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/secure-data')
            ->withHeader('Authorization', 'short')
            ->withHeader('Accept', 'application/json');

        $this->expectException(AbstractValidationError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function invalid_content_type_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream('some plain text'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function empty_content_type_header_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONTENT_TYPE_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withBody($this->psrFactory->createStream('{"value":"test"}'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_string_exceeding_max_length_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BODY_LIMITS_SPEC)
            ->build();

        $longTitle = str_repeat('a', 101);

        $request = $this->psrFactory->createServerRequest('POST', '/submit')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => $longTitle,
                'description' => 'Valid description',
            ])));

        $this->expectException(MaxLengthError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_array_exceeding_max_items_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BODY_LIMITS_SPEC)
            ->build();

        $tags = array_map(fn(int $i): string => 'tag' . $i, range(1, 11));

        $request = $this->psrFactory->createServerRequest('POST', '/submit')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => 'Valid title',
                'description' => 'Valid description',
                'tags' => $tags,
            ])));

        $this->expectException(AbstractValidationError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_object_exceeding_max_properties_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BODY_LIMITS_SPEC)
            ->build();

        $metadata = [];
        for ($i = 1; $i <= 6; ++$i) {
            $metadata['key' . $i] = 'value' . $i;
        }

        $request = $this->psrFactory->createServerRequest('POST', '/submit')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => 'Valid title',
                'description' => 'Valid description',
                'metadata' => $metadata,
            ])));

        $this->expectException(MaxPropertiesError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_with_additional_properties_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BODY_LIMITS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/submit')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => 'Valid title',
                'description' => 'Valid description',
                'unexpectedField' => 'should not be here',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_missing_required_field_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BODY_LIMITS_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/submit')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'description' => 'Valid description',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function path_parameter_with_wrong_pattern_throws_exception(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Path Pattern Test API
  version: 1.0.0
paths:
  /orders/{orderId}:
    get:
      summary: Get order
      parameters:
        - name: orderId
          in: path
          required: true
          schema:
            type: string
            pattern: '^ORD-[0-9]{6}$'
      responses:
        '200':
          description: Order found
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/orders/invalid-format');

        $this->expectException(PatternMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function response_with_missing_required_field_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RESPONSE_VALIDATION_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/abc');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'John',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function response_with_invalid_email_format_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RESPONSE_VALIDATION_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/abc');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'John',
                'email' => 'not-an-email',
            ])));

        $this->expectException(InvalidFormatException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function response_with_wrong_type_for_integer_field_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RESPONSE_VALIDATION_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/abc');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'not_an-integer',
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        $this->expectException(TypeMismatchError::class);
        $validator->validateResponse($response, $operation);
    }
}
