<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use Duyler\OpenApi\Validator\Error\ValidationContext;

class AnyOfValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private AnyOfValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new AnyOfValidator($this->pool);
    }

    #[Test]
    public function validate_when_at_least_one_schema_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
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
            anyOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function validate_when_all_schemas_valid(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_any_of_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_different_types(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'number');
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_wrong_type(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'number');
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(true, $schema);
    }

    #[Test]
    public function validate_empty_any_of(): void
    {
        $schema = new Schema(
            anyOf: [],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('any value', $schema);
    }

    #[Test]
    public function validate_any_of_with_second_schema_matching(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 10);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_any_of_single_schema(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            anyOf: [$schema1],
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_any_of_throws_exception_for_invalid_data(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'number');
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(new stdClass(), $schema);
    }

    #[Test]
    public function validate_any_of_with_nested_schemas(): void
    {
        $nestedSchema1 = new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')]);
        $nestedSchema2 = new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')]);
        $schema1 = new Schema(type: 'object', properties: ['address' => $nestedSchema1]);
        $schema2 = new Schema(type: 'object', properties: ['contact' => $nestedSchema2]);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $this->validator->validate(['address' => ['name' => 'John']], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_any_of_with_null_value_and_nullable_schema(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'string', nullable: true);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $this->validator->validate(null, $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_null_without_nullable_schema(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'string');
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $this->expectException(ValidationException::class);

        $this->validator->validate(null, $schema, $context);
    }

    #[Test]
    public function validate_any_of_with_context(): void
    {
        $schema1 = new Schema(type: 'string', minLength: 5);
        $schema2 = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            anyOf: [$schema1, $schema2],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $this->validator->validate('hello', $schema, $context);

        $this->expectNotToPerformAssertions();
    }
}
