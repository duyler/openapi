<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;

final class ResponseValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function status_code_200_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'John Doe',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_201_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'status' => 'created',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_400_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(400)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'error' => 'Bad request',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_404_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'error' => 'User not found',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_500_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(500)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'error' => 'Server error',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_range_2XX_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items/test-id');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'test-id',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_range_4XX_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items/test-id');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'error' => 'Not found',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function status_code_range_5XX_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items/test-id');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(500)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'error' => 'Server error',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function default_response_fallback_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/unknown/test-id');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(418)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'status' => 'I am a teapot',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function undefined_status_code_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/status-codes.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/550e8400-e29b-41d4-a716-446655440000');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(418)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'status' => 'teapot',
            ])));

        $this->expectException(UndefinedResponseException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function header_simple_string_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/simple');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Request-ID', '12345')
            ->withHeader('X-Rate-Limit', '100')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function header_array_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/array');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Encoding', 'gzip, deflate')
            ->withHeader('Allow', 'GET, POST, PUT, DELETE')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function header_content_type_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/content-type');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function header_content_length_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/content-length');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', '15')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function header_custom_format_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/custom-format');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Request-Date', '2024-01-01T00:00:00Z')
            ->withHeader('X-API-Version', '1.0.0')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function header_optional_required_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/headers.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/headers/optional-required');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Required-Header', 'value')
            ->withBody($this->psrFactory->createStream(json_encode(['id' => 'test'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_primitive_types_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/primitive');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'stringField' => 'hello',
                'numberField' => 42.5,
                'integerField' => 42,
                'booleanField' => true,
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_format_validation_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/formats');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'email' => 'test@example.com',
                'uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'dateTime' => '2024-01-01T00:00:00Z',
                'uri' => 'https://example.com',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_invalid_format_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/formats');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'email' => 'invalid-email',
                'uuid' => 'not-a-uuid',
                'dateTime' => 'not-a-date',
                'uri' => 'not-a-uri',
            ])));

        $this->expectException(InvalidFormatException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_optional_field_not_provided_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'requiredField' => 'value',
                'nullableRequiredField' => 'value',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_nested_objects_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nested');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
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

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_arrays_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/arrays');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'tags' => ['tag1', 'tag2', 'tag3'],
                'numbers' => [1, 2, 3.5],
                'objects' => [
                    ['id' => '1', 'name' => 'Item 1'],
                    ['id' => '2', 'name' => 'Item 2'],
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_array_too_many_items_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/arrays');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6'],
            ])));

        $this->expectException(MaxItemsError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_required_fields_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/required');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'test-id',
                'name' => 'Test Name',
                'description' => 'Optional description',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_missing_required_field_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/required');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'test-id',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_additional_properties_allowed_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/additional-properties');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'test-id',
                'extraField' => 'value',
                'anotherField' => 123,
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_anyof_composition_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/anyof');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 'string-value',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_anyof_integer_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/anyof');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 42,
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_allof_composition_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/allof');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'test-id',
                'name' => 'Test Name',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_form_data_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/other-content-types.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => '30',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_text_plain_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/other-content-types.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/text/plain');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream('Hello, World!'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_text_too_long_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/other-content-types.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/text/plain');
        $operation = $validator->validateRequest($request);

        $longText = str_repeat('a', 1001);
        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream($longText));

        $this->expectException(MaxLengthError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_binary_octet_stream_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/other-content-types.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/binary/octet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withBody($this->psrFactory->createStream('binary-data'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_binary_image_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/other-content-types.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/binary/image');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'image/png')
            ->withBody($this->psrFactory->createStream('image-data'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_invalid_type_mismatch_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/response-schemas.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/primitive');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'stringField' => 123,
                'numberField' => 'not-a-number',
                'integerField' => 'not-an-integer',
                'booleanField' => 'not-a-boolean',
            ])));

        $this->expectException(TypeMismatchError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_numeric_range_minimum_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: number
                    minimum: 10
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 5,
            ])));

        $this->expectException(MinimumError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_numeric_range_maximum_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: number
                    maximum: 100
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 150,
            ])));

        $this->expectException(MaximumError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_string_too_short_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: string
                    minLength: 5
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 'abc',
            ])));

        $this->expectException(MinLengthError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_string_too_long_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: string
                    maxLength: 10
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => 'this-is-a-very-long-string',
            ])));

        $this->expectException(MaxLengthError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_coercion_enabled_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  age:
                    type: integer
                  price:
                    type: number
                  active:
                    type: boolean
                  name:
                    type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'age' => '30',
                'price' => '99.99',
                'active' => 'true',
                'name' => 'John',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_coercion_disabled_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  age:
                    type: integer
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'age' => '30',
            ])));

        $this->expectException(TypeMismatchError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_form_data_coercion_enabled_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /form:
    post:
      responses:
        '200':
          description: Success
          content:
            application/x-www-form-urlencoded:
              schema:
                type: object
                properties:
                  age:
                    type: integer
                    minimum: 18
                  price:
                    type: number
                    minimum: 0
                  active:
                    type: boolean
                  name:
                    type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'age' => '30',
                'price' => '99.99',
                'active' => 'true',
                'name' => 'John',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_form_data_coercion_with_minimum_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /form:
    post:
      responses:
        '200':
          description: Success
          content:
            application/x-www-form-urlencoded:
              schema:
                type: object
                properties:
                  age:
                    type: integer
                    minimum: 18
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'age' => '20',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_form_data_coercion_with_minimum_below_throws_error(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /form:
    post:
      responses:
        '200':
          description: Success
          content:
            application/x-www-form-urlencoded:
              schema:
                type: object
                properties:
                  age:
                    type: integer
                    minimum: 18
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/form');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psrFactory->createStream(http_build_query([
                'age' => '15',
            ])));

        $this->expectException(MinimumError::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function body_json_nested_coercion_enabled_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  user:
                    type: object
                    properties:
                      age:
                        type: integer
                      active:
                        type: boolean
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => [
                    'age' => '25',
                    'active' => 'true',
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_array_coercion_enabled_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  items:
                    type: array
                    items:
                      type: integer
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'items' => ['1', '2', '3'],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_array_of_objects_coercion_enabled_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  users:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                        active:
                          type: boolean
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'users' => [
                    ['id' => '1', 'active' => 'true'],
                    ['id' => '2', 'active' => 'false'],
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_coercion_integer_truncation_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: integer
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => '30.5',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_coercion_boolean_variations_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  bool1:
                    type: boolean
                  bool2:
                    type: boolean
                  bool3:
                    type: boolean
                  bool4:
                    type: boolean
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'bool1' => 'true',
                'bool2' => '1',
                'bool3' => 'yes',
                'bool4' => 'on',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function body_json_coercion_number_to_float_valid(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  value:
                    type: number
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => '42',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_dog_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'petType' => 'dog',
                    'bark' => true,
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_cat_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'petType' => 'cat',
                    'meow' => true,
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_dog_missing_bark_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'petType' => 'dog',
                ],
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function discriminator_with_cat_missing_meow_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'petType' => 'cat',
                ],
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function discriminator_with_invalid_type_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'petType' => 'bird',
                ],
            ])));

        $this->expectException(RuntimeException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function discriminator_with_missing_property_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/discriminator-responses.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/pet');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'pet' => [
                    'bark' => true,
                ],
            ])));

        $this->expectException(RuntimeException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function nullable_field_with_null_value_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '123',
                'nullableField' => null,
                'nullableRequiredField' => null,
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_field_with_non_null_value_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '123',
                'nullableField' => 'value',
                'nullableRequiredField' => 'value',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_optional_field_missing_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '123',
                'nullableRequiredField' => 'value',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_required_field_missing_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '123',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function nullable_field_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '123',
                'nullableField' => null,
                'nullableRequiredField' => 'value',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nested_nullable_field_with_null_value_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-nested');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => [
                    'name' => 'John Doe',
                    'email' => null,
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_nullable_items_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/nullable.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-array');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value1', null, 'value3',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
