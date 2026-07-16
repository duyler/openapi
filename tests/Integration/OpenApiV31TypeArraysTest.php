<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

final class OpenApiV31TypeArraysTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function validates_nullable_string_with_null_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-primitives');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'nullableString' => null,
                'nullableNumber' => 42.5,
                'nullableInteger' => 10,
                'nullableBoolean' => true,
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_nullable_string_with_string_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-primitives');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'nullableString' => 'hello',
                'nullableNumber' => null,
                'nullableInteger' => null,
                'nullableBoolean' => null,
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_nullable_array_with_null_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-complex');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'nullableArray' => null,
                'nullableObject' => null,
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_nullable_array_with_array_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-complex');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'nullableArray' => ['item1', 'item2'],
                'nullableObject' => ['name' => 'test'],
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_multiple_type_union(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/multiple-types');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'stringOrNumber' => 'text',
                'stringOrNumberOrNull' => null,
                'stringOrInteger' => 42,
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_nested_nullable_fields(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nested-nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => [
                    'id' => 1,
                    'name' => 'John',
                    'email' => null,
                    'phone' => '+1234567890',
                ],
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_array_with_nullable_items(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/array-items-nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'item1',
                null,
                'item3',
                null,
                'item5',
            ], JSON_THROW_ON_ERROR)));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_request_body_with_type_arrays(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/request-body')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'Test Resource',
                'description' => null,
                'age' => 25,
            ], JSON_THROW_ON_ERROR)));

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_component_schema_with_type_arrays(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.1/type-arrays.yaml')
            ->build();

        $data = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => null,
            'metadata' => null,
        ];

        $validator->validateSchema($data, '#/components/schemas/NullableUser');

        $this->expectNotToPerformAssertions();
    }
}
