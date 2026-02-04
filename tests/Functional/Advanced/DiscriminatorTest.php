<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Override;
use PHPUnit\Framework\Attributes\Test;

final class DiscriminatorTest extends AdvancedFunctionalTestCase
{
    private string $specFile = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/discriminator.yaml';
    }

    #[Test]
    public function simple_discriminator_with_cat_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/simple', [
            'petType' => 'cat',
            'meow' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function simple_discriminator_with_dog_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/simple', [
            'petType' => 'dog',
            'bark' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_missing_property_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/simple', [
            'name' => 'Fluffy',
        ]);

        $this->expectException(MissingDiscriminatorPropertyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function discriminator_invalid_type_value_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/simple', [
            'petType' => 123,
        ]);

        $this->expectException(InvalidDiscriminatorValueException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function discriminator_unknown_value_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/simple', [
            'petType' => 'bird',
        ]);

        $this->expectException(UnknownDiscriminatorValueException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function discriminator_with_allof_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/allof', [
            'petType' => 'cat',
            'meow' => true,
            'name' => 'Fluffy',
            'age' => 3,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_anyof_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/anyof', [
            'petType' => 'dog',
            'bark' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_in_array_of_objects_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/array', [
            'pets' => [
                [
                    'petType' => 'cat',
                    'meow' => true,
                ],
                [
                    'petType' => 'dog',
                    'bark' => true,
                ],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_explicit_mapping_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/explicit-mapping', [
            'type' => 'cat',
            'petType' => 'cat',
            'meow' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_implicit_mapping_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/implicit-mapping', [
            'type' => 'cat',
            'petType' => 'cat',
            'meow' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_mixed_mapping_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/mixed-mapping', [
            'type' => 'cat',
            'petType' => 'cat',
            'meow' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_multiple_inheritance_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/pet/inheritance', [
            'type' => 'kitten',
            'meow' => true,
            'cute' => true,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }
}
