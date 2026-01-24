<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class AllOfValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private AllOfValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new AllOfValidator($this->pool);
    }

    #[Test]
    public function validate_when_all_schemas_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_any_schema_invalid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string', maxLength: 5);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function throw_error_when_single_schema_invalid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema2 = new Schema(type: 'string', maxLength: 3);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_when_all_of_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_three_schemas(): void
    {
        $schema1 = new Schema(type: 'number', minimum: 5);
        $schema2 = new Schema(type: 'number', maximum: 15);
        $schema3 = new Schema(type: 'number', multipleOf: 5);
        $schema = new Schema(
            allOf: [$schema1, $schema2, $schema3],
        );

        $this->validator->validate(10, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_multiple_schemas_fail(): void
    {
        $schema1 = new Schema(type: 'number', minimum: 10);
        $schema2 = new Schema(type: 'number', maximum: 5);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(7, $schema);
    }

    #[Test]
    public function validate_empty_all_of(): void
    {
        $schema = new Schema(
            allOf: [],
        );

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_all_of_with_first_schema_failing(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string');
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function validate_all_of_with_second_schema_failing(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'string', maxLength: 3);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function validate_all_of_single_schema(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            allOf: [$schema1],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_all_of_throws_exception_for_invalid_data(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'string');
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(new stdClass(), $schema);
    }

    #[Test]
    public function validate_all_of_with_nested_schemas(): void
    {
        $nestedSchema1 = new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')]);
        $nestedSchema2 = new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')]);
        $schema1 = new Schema(type: 'object', properties: ['address' => $nestedSchema1]);
        $schema2 = new Schema(type: 'object', properties: ['contact' => $nestedSchema2]);
        $schema = new Schema(
            allOf: [$schema1, $schema2],
        );

        $this->validator->validate([
            'address' => ['name' => 'John'],
            'contact' => ['age' => 30],
        ], $schema);

        $this->expectNotToPerformAssertions();
    }
}
