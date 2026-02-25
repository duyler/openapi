<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;

final class ReferenceResolutionTest extends AdvancedFunctionalTestCase
{
    private string $specFile = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/complex-references.yaml';
    }

    #[Test]
    public function local_ref_to_schema_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/schema-ref?id=user-123&name=John+Doe');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            'id' => 'user-123',
            'name' => 'John Doe',
        ]);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function local_ref_to_schema_missing_required_throws(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/schema-ref?id=user-123&name=John+Doe');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            'foo' => 'bar',
        ]);

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function local_ref_to_parameter_valid(): void
    {
        $validator = $this->createValidatorWithCoercion();
        $request = $this->createRequest('GET', '/parameter-ref?limit=10');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function local_ref_to_response_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/response-ref');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            'id' => 'user-123',
            'name' => 'John Doe',
        ]);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ref_inside_allof_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/allof-ref', [
            'id' => 'user-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ref_inside_items_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/items-ref', [
            'users' => [
                [
                    'id' => '1',
                    'name' => 'John',
                ],
                [
                    'id' => '2',
                    'name' => 'Jane',
                ],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ref_inside_prefixItems_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/prefixitems-ref', [
            'data' => [
                'string-value',
                42,
                true,
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nested_ref_resolution_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/nested-ref', [
            'company' => [
                'users' => [
                    [
                        'id' => '1',
                        'name' => 'John',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function invalid_ref_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/invalid-ref');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            'test' => 'data',
        ]);

        $this->expectException(UnresolvableRefException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function ref_with_additional_properties_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/additional-props-ref', [
            'id' => '1',
            'name' => 'John',
            'customField' => 'custom-value',
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function recursive_ref_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/recursive-ref', [
            'id' => '1',
            'name' => 'Category 1',
            'parent' => [
                'id' => '2',
                'name' => 'Category 2',
                'parent' => [
                    'id' => '3',
                    'name' => 'Category 3',
                ],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    private function createValidatorWithCoercion(): OpenApiValidator
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
    }
}
