<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Override;
use PHPUnit\Framework\Attributes\Test;

final class TypeCoercionTest extends AdvancedFunctionalTestCase
{
    private string $specFile = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/type-coercion.yaml';
    }

    #[Test]
    public function string_to_integer_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?age=30');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_to_float_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?price=99.99');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coercion_with_mixed_types_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('POST', '/request/mixed', [
            'data' => [
                'id' => 123,
                'count' => '5',
                'active' => 'yes',
                'tags' => ['tag1', 'tag2', 'tag3'],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_to_string_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?name=123');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boolean_string_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?active=true');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boolean_integer_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?active=1');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coercion_enabled_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?age=25&price=100.50&active=yes');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coercion_disabled_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/request/coercion?age=25');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function nested_object_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('POST', '/request/nested', [
            'user' => [
                'age' => '25',
                'active' => 'true',
            ],
            'items' => ['1', '2', '3'],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_items_coercion_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('POST', '/request/array', [
            'numbers' => ['1', '2', '3'],
            'booleans' => ['true', 'false', '1'],
            'nested' => [
                ['id' => 1, 'value' => '10.5'],
                ['id' => 2, 'value' => '20.7'],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coercion_with_nullable_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->enableNullableAsType()
            ->build();
        $request = $this->createRequest('POST', '/request/nullable', [
            'nullableInt' => '42',
            'nullableString' => null,
            'nullableBool' => 'yes',
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coercion_with_multiple_parameters_valid(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();
        $request = $this->createRequest('GET', '/request/coercion?age=25&price=100.50&active=true&name=test');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }
}
