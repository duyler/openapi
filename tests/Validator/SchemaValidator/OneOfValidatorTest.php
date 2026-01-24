<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OneOfValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private OneOfValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new OneOfValidator($this->pool);
    }

    #[Test]
    public function validate_when_exactly_one_schema_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            oneOf: [$schema1, $schema2],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_no_schema_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string', maxLength: 3);
        $schema = new Schema(
            oneOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function throw_error_when_multiple_schemas_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            oneOf: [$schema1, $schema2],
        );

        $this->expectException(OneOfError::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_when_one_of_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_three_schemas_one_valid(): void
    {
        $schema1 = new Schema(type: 'number', minimum: 10);
        $schema2 = new Schema(type: 'number', maximum: 5);
        $schema3 = new Schema(type: 'number', multipleOf: 5);
        $schema = new Schema(
            oneOf: [$schema1, $schema2, $schema3],
        );

        $this->validator->validate(12, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_different_types(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'number');
        $schema = new Schema(
            oneOf: [$schema1, $schema2],
        );

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_one_of(): void
    {
        $schema = new Schema(
            oneOf: [],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('any value', $schema);
    }

    #[Test]
    public function validate_one_of_single_schema(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            oneOf: [$schema1],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_one_of_with_nested_schemas(): void
    {
        $schema1 = new Schema(
            type: 'object',
            properties: [
                'type' => new Schema(type: 'string', enum: ['person']),
                'name' => new Schema(type: 'string'),
            ],
        );
        $schema2 = new Schema(
            type: 'object',
            properties: [
                'type' => new Schema(type: 'string', enum: ['company']),
                'companyName' => new Schema(type: 'string'),
            ],
        );
        $schema = new Schema(
            oneOf: [$schema1, $schema2],
        );

        $this->validator->validate(['type' => 'person', 'name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
