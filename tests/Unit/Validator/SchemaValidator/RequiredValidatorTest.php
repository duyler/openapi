<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequiredValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private RequiredValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new RequiredValidator($this->pool);
    }

    #[Test]
    public function validate_when_all_required_fields_present(): void
    {
        $schema = new Schema(type: 'object', required: ['name', 'age']);

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_single_missing_field(): void
    {
        $schema = new Schema(type: 'object', required: ['name', 'age']);

        $this->expectException(ValidationException::class);

        $this->validator->validate(['name' => 'John'], $schema);
    }

    #[Test]
    public function throw_error_for_multiple_missing_fields(): void
    {
        $schema = new Schema(type: 'object', required: ['name', 'age', 'email']);

        $this->expectException(ValidationException::class);

        $this->validator->validate(['city' => 'NYC'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $schema = new Schema(type: 'string', required: ['name']);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allow_null_value_for_required_field(): void
    {
        $schema = new Schema(type: 'object', required: ['name']);

        $this->validator->validate(['name' => null], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_required_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_required_is_empty(): void
    {
        $schema = new Schema(type: 'object', required: []);

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_object_when_no_required_fields(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_object_with_required_fields(): void
    {
        $schema = new Schema(type: 'object', required: ['name']);

        $this->expectException(ValidationException::class);

        $this->validator->validate([], $schema);
    }

    #[Test]
    public function use_array_key_exists_not_isset(): void
    {
        $schema = new Schema(type: 'object', required: ['field']);

        $this->validator->validate(['field' => null], $schema);

        $this->expectNotToPerformAssertions();
    }
}
