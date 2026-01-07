<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PrefixItemsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PrefixItemsValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PrefixItemsValidator($this->pool);
    }

    #[Test]
    public function validate_tuple_items(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $this->validator->validate(['hello', 42, true], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_additional_items_with_items_schema(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $additionalSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            items: $additionalSchema,
        );

        $this->validator->validate(['hello', 42, 3.14, 2.71], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_tuple_item(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(['hello', 'world'], $schema);
    }

    #[Test]
    public function throw_error_for_invalid_additional_item(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $additionalSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
            items: $additionalSchema,
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(['hello', 'world'], $schema);
    }

    #[Test]
    public function validate_fewer_items_than_prefix_items(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $this->validator->validate(['hello', 42], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_associative_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_prefix_items_is_null(): void
    {
        $schema = new Schema(type: 'array');

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_prefix_items_is_empty(): void
    {
        $schema = new Schema(type: 'array', prefixItems: []);

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allow_additional_items_when_no_items_schema(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
        );

        $this->validator->validate(['hello', 42, true, 'extra'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
