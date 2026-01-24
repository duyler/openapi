<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TypeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private TypeValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new TypeValidator($this->pool);
    }

    #[Test]
    public function validate_string_type(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_number_type_with_float(): void
    {
        $schema = new Schema(type: 'number');

        $this->validator->validate(3.14, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_number_type_with_integer(): void
    {
        $schema = new Schema(type: 'number');

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_integer_type(): void
    {
        $schema = new Schema(type: 'integer');

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_boolean_type(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->validator->validate(true, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_null_type(): void
    {
        $schema = new Schema(type: 'null');

        $this->validator->validate(null, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_array_type(): void
    {
        $schema = new Schema(type: 'array');

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_object_type(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_type(): void
    {
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(123, $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_string_for_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate('123', $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_object_for_array(): void
    {
        $schema = new Schema(type: 'array');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(['key' => 'value'], $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_array_for_object(): void
    {
        $schema = new Schema(type: 'object');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([1, 2, 3], $schema);
    }

    #[Test]
    public function skip_validation_when_type_is_null(): void
    {
        $schema = new Schema(type: null);

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_type_mismatch_error_for_null_when_not_null_type(): void
    {
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(null, $schema);
    }

    #[Test]
    public function validate_type_multiple_types(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_type_multiple_types_with_number(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_type_mismatch_for_multiple_types(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(true, $schema);
    }
}
