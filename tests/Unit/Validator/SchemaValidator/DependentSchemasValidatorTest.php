<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DependentSchemasValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private DependentSchemasValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new DependentSchemasValidator($this->pool);
    }

    #[Test]
    public function apply_dependent_schema_when_property_present(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->validator->validate(['creditCard' => '1234567890', 'billingAddress' => '123 Main St'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_property_absent(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_dependent_schema_fails(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['creditCard' => '1234567890'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $dependentSchema = new Schema(type: 'object');
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key' => $dependentSchema,
            ],
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_schemas_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_schemas_is_empty(): void
    {
        $schema = new Schema(type: 'object', dependentSchemas: []);

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_multiple_dependent_schemas(): void
    {
        $dependentSchema1 = new Schema(
            type: 'object',
            required: ['field1'],
        );
        $dependentSchema2 = new Schema(
            type: 'object',
            required: ['field2'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key1' => $dependentSchema1,
                'key2' => $dependentSchema2,
            ],
        );

        $this->validator->validate([
            'key1' => 'value1',
            'key2' => 'value2',
            'field1' => 'value1',
            'field2' => 'value2',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_only_matching_dependent_schemas(): void
    {
        $dependentSchema1 = new Schema(
            type: 'object',
            required: ['field1'],
        );
        $dependentSchema2 = new Schema(
            type: 'object',
            required: ['field2'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key1' => $dependentSchema1,
                'key2' => $dependentSchema2,
            ],
        );

        $this->validator->validate([
            'key1' => 'value1',
            'field1' => 'value1',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }
}
