<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;
use PHPUnit\Framework\Attributes\Test;

final class RefItemsValidationTest extends AdvancedFunctionalTestCase
{
    private string $specFile;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/ref-items-validation.yaml';
    }

    #[Test]
    public function items_with_ref_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/items');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            [
                'id' => 1,
            ],
        ]);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function items_with_ref_missing_required_field_throws(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/items');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            [
                'foo' => 'bar',
            ],
        ]);

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function items_with_ref_invalid_type_throws(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/items');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            [
                'id' => 'invalid-string',
            ],
        ]);

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function items_with_ref_below_minimum_throws(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/items');

        $operation = $validator->validateRequest($request);
        $response = $this->createResponse(200, [
            [
                'id' => 0,
            ],
        ]);

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }
}
