<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MinPropertiesError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ObjectLengthValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ObjectLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ObjectLengthValidator($this->pool);
    }

    #[Test]
    public function validate_min_properties(): void
    {
        $schema = new Schema(type: 'object', minProperties: 2);

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_max_properties(): void
    {
        $schema = new Schema(type: 'object', maxProperties: 5);

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_both_min_and_max(): void
    {
        $schema = new Schema(type: 'object', minProperties: 2, maxProperties: 5);

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_properties_error(): void
    {
        $schema = new Schema(type: 'object', minProperties: 3);

        $this->expectException(MinPropertiesError::class);

        $this->validator->validate(['name' => 'John'], $schema);
    }

    #[Test]
    public function throw_max_properties_error(): void
    {
        $schema = new Schema(type: 'object', maxProperties: 2);

        $this->expectException(MaxPropertiesError::class);

        $this->validator->validate(['name' => 'John', 'age' => 30, 'city' => 'NYC'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $schema = new Schema(type: 'string', minProperties: 3);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_object(): void
    {
        $schema = new Schema(type: 'object', minProperties: 0);

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_object_when_min_greater_than_zero(): void
    {
        $schema = new Schema(type: 'object', minProperties: 1);

        $this->expectException(MinPropertiesError::class);

        $this->validator->validate([], $schema);
    }

    #[Test]
    public function skip_when_no_constraints(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_boundary_values(): void
    {
        $schema = new Schema(type: 'object', minProperties: 1, maxProperties: 3);

        $this->validator->validate(['name' => 'John'], $schema);
        $this->validator->validate(['name' => 'John', 'age' => 30, 'city' => 'NYC'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function count_numeric_array_keys(): void
    {
        $schema = new Schema(type: 'object', minProperties: 2);

        $this->validator->validate([0 => 'a', 1 => 'b'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
