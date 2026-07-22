<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

final class FixtureE2ETest extends TestCase
{
    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function large_payloads_fixture_validates_large_object(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/large-payloads.yaml')
            ->build();

        $data = [];
        for ($i = 1; $i <= 20; ++$i) {
            $data['field' . $i] = 'value_' . $i;
        }

        $request = $this->psrFactory->createServerRequest('POST', '/test/large')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($data)));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function large_payloads_fixture_validates_large_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/large-payloads.yaml')
            ->build();

        $items = [];
        for ($i = 0; $i < 50; ++$i) {
            $items[] = ['id' => $i, 'name' => 'Item ' . $i, 'value' => 'Value ' . $i];
        }

        $request = $this->psrFactory->createServerRequest('POST', '/test/large')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'field1' => 'test',
                'field2' => json_encode($items),
            ])));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function large_payloads_fixture_validates_null_handling(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/large-payloads.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/test/large/null')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'nullable_field' => null,
                'required_field' => 'value',
                'empty_string_field' => '',
            ])));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function pagination_fixture_validates_paginated_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/pagination.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/api/users?page=2&limit=50&offset=50');

        $operation = $validator->validateRequest($request);
        $this->assertSame('GET', $operation->method);
        $this->assertSame('/api/users', $operation->path);
    }

    #[Test]
    public function pagination_fixture_validates_paginated_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/pagination.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/api/users?page=1&limit=10');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'data' => [
                    ['id' => 1, 'name' => 'User One'],
                    ['id' => 2, 'name' => 'User Two'],
                ],
                'pagination' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 25,
                    'totalPages' => 3,
                ],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pagination_fixture_rejects_invalid_page_parameter(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/pagination.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/api/users?page=0&limit=10');

        $this->expectException(MinimumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function filtering_fixture_validates_filtered_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/filtering.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/api/products?status=active&minPrice=10&maxPrice=100&sort=name&order=asc',
        );

        $operation = $validator->validateRequest($request);
        $this->assertSame('GET', $operation->method);
        $this->assertSame('/api/products', $operation->path);
    }

    #[Test]
    public function filtering_fixture_rejects_invalid_status_enum(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/filtering.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/api/products?status=unknown_status',
        );

        $this->expectException(EnumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function crud_operations_fixture_validates_create_user(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/crud-operations.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/api/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'email' => 'john@example.com',
                'password' => 'StrongP@ss1',
                'name' => 'John Doe',
                'age' => 25,
            ])));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
        $this->assertSame('/api/users', $operation->path);
    }

    #[Test]
    public function crud_operations_fixture_validates_get_user(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/crud-operations.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/api/users/42');

        $operation = $validator->validateRequest($request);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function crud_operations_fixture_validates_delete_user(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/crud-operations.yaml')
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('DELETE', '/api/users/1');

        $operation = $validator->validateRequest($request);
        $this->assertSame('DELETE', $operation->method);
    }

    #[Test]
    public function crud_operations_fixture_validates_full_cycle_request_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world/crud-operations.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/api/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'email' => 'jane@example.com',
                'password' => 'StrongP@ss1',
                'name' => 'Jane Doe',
            ])));

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'email' => 'jane@example.com',
                'name' => 'Jane Doe',
                'createdAt' => '2026-01-15T10:30:00Z',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boundary_values_fixture_validates_integer_boundaries(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/boundary-values.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/test/boundary')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'int32_max' => 2147483647,
                'int32_min' => -2147483648,
                'zero' => 0,
                'negative' => -1,
            ])));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function boundary_values_fixture_rejects_integer_above_max(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/boundary-values.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/test/boundary')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'int32_max' => 2147483648,
            ])));

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        // int32 format validator (registered per R3-SPEC-013) fires before
        // the `maximum` keyword for values exceeding the int32 range.
        // Either MaximumError or InvalidFormatException is a correct rejection.
        self::assertTrue(
            $caught instanceof MaximumError || $caught instanceof InvalidFormatException,
            'Expected MaximumError or InvalidFormatException for int32_max above range',
        );
    }

    #[Test]
    public function boundary_values_fixture_validates_string_boundaries(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/boundary-values.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/test/boundary/string')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'empty_string' => '',
                'min_length' => 'abcde',
                'max_length' => '1234567890',
                'exact_length' => '1234567',
            ])));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function complex_nesting_fixture_validates_deep_nesting(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/complex-nesting.yaml')
            ->build();

        $nestedData = ['level10' => 'deep value'];
        for ($i = 9; $i >= 1; --$i) {
            $nestedData = ['level' . $i => $nestedData];
        }

        $request = $this->psrFactory->createServerRequest('POST', '/test/nesting')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($nestedData)));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function complex_nesting_fixture_validates_mixed_nesting(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/complex-nesting.yaml')
            ->build();

        $data = [
            'data' => [
                'users' => [
                    [
                        'profile' => [
                            'settings' => [
                                'preferences' => [
                                    ['value' => 'dark_mode'],
                                    ['value' => 'notifications_on'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->psrFactory->createServerRequest('POST', '/test/nesting/mixed')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($data)));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function complex_nesting_fixture_validates_nested_arrays_and_objects(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/edge-cases/complex-nesting.yaml')
            ->build();

        $data = [
            'items' => [
                [
                    'name' => 'Item 1',
                    'tags' => ['tag1', 'tag2'],
                    'metadata' => [
                        'attributes' => [
                            ['key' => 'color', 'value' => 'red'],
                            ['key' => 'size', 'value' => 'large'],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->psrFactory->createServerRequest('POST', '/test/nesting/arrays')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($data)));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }
}
