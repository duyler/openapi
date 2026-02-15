<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

final class RequestValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function path_parameter_with_uuid_format_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/users/{userId}', $operation->path);
    }

    #[Test]
    public function path_parameter_with_integer_type_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/products/1234');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/products/{productId}', $operation->path);
    }

    #[Test]
    public function path_parameter_with_pattern_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/orders/ORD-123456');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/orders/{orderId}', $operation->path);
    }

    #[Test]
    public function path_parameter_with_pattern_invalid_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/orders/INVALID-ORDER');

        $this->expectException(PatternMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function multiple_path_parameters_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/complex-schemas.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/articles/550e8400-e29b-41d4-a716-446655440000/comments/42',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/articles/{articleId}/comments/{commentId}', $operation->path);
    }

    #[Test]
    public function path_parameter_with_minimum_value_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/products/1');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/products/{productId}', $operation->path);
    }

    #[Test]
    public function path_parameter_with_minimum_value_invalid_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/products/0');

        $this->expectException(MinimumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function query_parameter_with_boolean_type_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000?includeProfile=true');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function query_parameter_with_enum_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/products/1234?format=json');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function query_parameter_with_enum_invalid_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/products/1234?format=invalid');

        $this->expectException(EnumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function query_parameter_array_form_style_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/complex-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items/123?tags=tag1,tag2,tag3');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function query_parameter_array_pipe_delimited_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Complex Schemas API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      summary: Get item with complex query
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: ids
          in: query
          style: pipeDelimited
          explode: false
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Item found
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items/123?ids=id1|id2|id3');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function query_parameter_object_deep_object_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/complex-schemas.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/123?filters[category]=electronics&filters[minPrice]=10.99&filters[maxPrice]=99.99',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function optional_query_parameter_not_provided_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/simple-params.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function header_parameter_simple_type_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Request-ID
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Request-ID', '12345');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function header_parameter_missing_required_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Request-ID
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');

        $this->expectException(MissingParameterException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_parameter_case_insensitive_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Request-ID
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('x-request-id', '12345');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function cookie_parameter_simple_type_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Cookies API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: session
          in: cookie
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withCookieParams(['session' => 'abc123']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function cookie_parameter_missing_required_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Cookies API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: session
          in: cookie
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');

        $this->expectException(MissingParameterException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function multiple_cookies_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Cookies API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: session
          in: cookie
          required: true
          schema:
            type: string
        - name: userId
          in: cookie
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withCookieParams(['session' => 'abc123', 'userId' => 'user456']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function request_body_json_simple_types_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
                - age
              properties:
                name:
                  type: string
                age:
                  type: integer
                active:
                  type: boolean
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John Doe',
                'age' => 30,
                'active' => true,
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_missing_required_field_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
                - age
              properties:
                name:
                  type: string
                age:
                  type: integer
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John Doe',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_nested_objects_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - user
              properties:
                user:
                  type: object
                  properties:
                    name:
                      type: string
                    email:
                      type: string
                    address:
                      type: object
                      properties:
                        street:
                          type: string
                        city:
                          type: string
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'address' => [
                        'street' => '123 Main St',
                        'city' => 'New York',
                    ],
                ],
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_array_of_objects_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /orders:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - items
              properties:
                items:
                  type: array
                  items:
                    type: object
                    required:
                      - id
                      - quantity
                    properties:
                      id:
                        type: string
                      quantity:
                        type: integer
                        minimum: 1
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'items' => [
                    ['id' => 'item1', 'quantity' => 2],
                    ['id' => 'item2', 'quantity' => 5],
                ],
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_form_data_simple_fields_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/form-data.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form-submit')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => '30',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_form_data_with_email_format_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/form-data.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form-submit')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'name' => 'John Doe',
                'email' => 'test@example.com',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_form_data_with_invalid_email_format_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/form-data.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form-submit')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'name' => 'John Doe',
                'email' => 'invalid-email',
            ])));

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_form_data_missing_required_field_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/request-validation-specs/form-data.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form-submit')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'name' => 'John Doe',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_text_plain_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Text API
  version: 1.0.0
paths:
  /text:
    post:
      requestBody:
        required: true
        content:
          text/plain:
            schema:
              type: string
              minLength: 1
              maxLength: 1000
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/text')
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream('Hello, World!'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_unsupported_media_type_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Text API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream('<data>test</data>'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_additional_properties_allowed_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
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
              additionalProperties: true
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John Doe',
                'extraField' => 'some value',
                'anotherField' => 123,
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_additional_properties_forbidden_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
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
              additionalProperties: false
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John Doe',
                'extraField' => 'some value',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_array_length_constraints_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                tags:
                  type: array
                  items:
                    type: string
                  minItems: 1
                  maxItems: 5
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'tags' => ['tag1', 'tag2', 'tag3'],
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_array_too_many_items_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                tags:
                  type: array
                  items:
                    type: string
                  maxItems: 5
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6'],
            ])));

        $this->expectException(MaxItemsError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_string_length_constraints_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - title
              properties:
                title:
                  type: string
                  minLength: 5
                  maxLength: 100
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => 'Valid Title',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_string_too_short_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - title
              properties:
                title:
                  type: string
                  minLength: 5
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'title' => 'abc',
            ])));

        $this->expectException(MinLengthError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_numeric_range_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - price
              properties:
                price:
                  type: number
                  minimum: 0
                  maximum: 1000
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'price' => 99.99,
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function request_body_json_numeric_below_minimum_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - price
              properties:
                price:
                  type: number
                  minimum: 0
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'price' => -10,
            ])));

        $this->expectException(MinimumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_json_numeric_above_maximum_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: JSON Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - price
              properties:
                price:
                  type: number
                  maximum: 1000
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'price' => 1500,
            ])));

        $this->expectException(MaximumError::class);
        $validator->validateRequest($request);
    }
}
