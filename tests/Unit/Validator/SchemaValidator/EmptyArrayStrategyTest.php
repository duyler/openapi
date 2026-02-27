<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmptyArrayStrategyTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function empty_array_with_prefer_array_strategy_valid_for_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferArray);
        $schema = new Schema(type: 'array');

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_with_prefer_array_strategy_invalid_for_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferArray);
        $schema = new Schema(type: 'object');

        $this->expectException(TypeMismatchError::class);

        $validator->validate([], $schema, $context);
    }

    #[Test]
    public function empty_array_with_prefer_object_strategy_invalid_for_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferObject);
        $schema = new Schema(type: 'array');

        $this->expectException(TypeMismatchError::class);

        $validator->validate([], $schema, $context);
    }

    #[Test]
    public function empty_array_with_prefer_object_strategy_valid_for_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferObject);
        $schema = new Schema(type: 'object');

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_with_reject_strategy_invalid_for_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::Reject);
        $schema = new Schema(type: 'array');

        $this->expectException(TypeMismatchError::class);

        $validator->validate([], $schema, $context);
    }

    #[Test]
    public function empty_array_with_reject_strategy_invalid_for_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::Reject);
        $schema = new Schema(type: 'object');

        $this->expectException(TypeMismatchError::class);

        $validator->validate([], $schema, $context);
    }

    #[Test]
    public function empty_array_with_allow_both_strategy_valid_for_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::AllowBoth);
        $schema = new Schema(type: 'array');

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_with_allow_both_strategy_valid_for_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::AllowBoth);
        $schema = new Schema(type: 'object');

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_in_union_type_with_prefer_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferArray);
        $schema = new Schema(type: ['array', 'object']);

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_in_union_type_with_prefer_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferObject);
        $schema = new Schema(type: ['array', 'object']);

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_in_union_type_with_reject(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::Reject);
        $schema = new Schema(type: ['array', 'object']);

        $this->expectException(TypeMismatchError::class);

        $validator->validate([], $schema, $context);
    }

    #[Test]
    public function empty_array_in_union_type_with_allow_both(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::AllowBoth);
        $schema = new Schema(type: ['array', 'object']);

        $validator->validate([], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function strategy_configured_via_builder(): void
    {
        $spec = '{"openapi":"3.0.3","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($spec)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
            ->build();

        $this->assertSame(EmptyArrayStrategy::PreferArray, $validator->emptyArrayStrategy);
    }

    #[Test]
    public function default_strategy_is_allow_both(): void
    {
        $spec = '{"openapi":"3.0.3","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($spec)
            ->build();

        $this->assertSame(EmptyArrayStrategy::AllowBoth, $validator->emptyArrayStrategy);
    }

    #[Test]
    public function non_empty_array_validation_unchanged_with_prefer_array(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferArray);

        $arraySchema = new Schema(type: 'array');
        $objectSchema = new Schema(type: 'object');

        $validator->validate([1, 2, 3], $arraySchema, $context);
        $validator->validate(['key' => 'value'], $objectSchema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_empty_object_validation_unchanged_with_prefer_object(): void
    {
        $validator = new TypeValidator($this->pool);
        $context = ValidationContext::create($this->pool, true, EmptyArrayStrategy::PreferObject);

        $arraySchema = new Schema(type: 'array');
        $objectSchema = new Schema(type: 'object');

        $validator->validate([1, 2, 3], $arraySchema, $context);
        $validator->validate(['key' => 'value'], $objectSchema, $context);

        $this->expectNotToPerformAssertions();
    }
}
