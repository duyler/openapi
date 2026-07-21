<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemsValidator::class)]
#[CoversClass(ContainsValidator::class)]
#[CoversClass(PropertyNamesValidator::class)]
#[CoversClass(IfThenElseValidator::class)]
#[CoversClass(NotValidator::class)]
#[CoversClass(UnevaluatedItemsValidator::class)]
final class BooleanSchemaKeywordTest extends TestCase
{
    private ValidatorPool $pool;
    private ValidatorDependencies $dependencies;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->dependencies = new ValidatorDependencies(
            pool: $this->pool,
            formatRegistry: BuiltinFormats::create(),
        );
    }

    #[Test]
    public function items_true_accepts_any_array(): void
    {
        $validator = new ItemsValidator($this->dependencies);
        $schema = new Schema(type: 'array', items: true);

        $validator->validate([1, 'a', null, ['nested']], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function items_false_rejects_every_element(): void
    {
        $validator = new ItemsValidator($this->dependencies);
        $schema = new Schema(type: 'array', items: false);

        $this->expectException(ValidationException::class);

        $validator->validate([1], $schema);
    }

    #[Test]
    public function items_false_error_carries_type_mismatch(): void
    {
        $validator = new ItemsValidator($this->dependencies);
        $schema = new Schema(type: 'array', items: false);

        try {
            $validator->validate([1], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertNotEmpty($errors);
            self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        }
    }

    #[Test]
    public function items_false_skips_elements_covered_by_prefix_items(): void
    {
        $validator = new ItemsValidator($this->dependencies);
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'integer')],
            items: false,
        );

        $validator->validate([1], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function contains_true_accepts_any_non_empty_array(): void
    {
        $validator = new ContainsValidator($this->dependencies);
        $schema = new Schema(type: 'array', contains: true);

        $validator->validate([1], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function contains_true_empty_array_fails_min_contains_default(): void
    {
        $validator = new ContainsValidator($this->dependencies);
        $schema = new Schema(type: 'array', contains: true);

        $this->expectException(ContainsMatchError::class);

        $validator->validate([], $schema);
    }

    #[Test]
    public function contains_false_never_matches_non_empty_array(): void
    {
        $validator = new ContainsValidator($this->dependencies);
        $schema = new Schema(type: 'array', contains: false);

        $this->expectException(ContainsMatchError::class);

        $validator->validate([1], $schema);
    }

    #[Test]
    public function contains_false_with_zero_min_contains_passes(): void
    {
        $validator = new ContainsValidator($this->dependencies);
        $schema = new Schema(type: 'array', contains: false, minContains: 0);

        $validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function property_names_true_accepts_any_property_name(): void
    {
        $validator = new PropertyNamesValidator($this->dependencies);
        $schema = new Schema(type: 'object', propertyNames: true);

        $validator->validate(['any-key' => 1, 'another' => 2], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function property_names_false_rejects_every_property_name(): void
    {
        $validator = new PropertyNamesValidator($this->dependencies);
        $schema = new Schema(type: 'object', propertyNames: false);

        $this->expectException(ValidationException::class);

        $validator->validate(['k' => 1], $schema);
    }

    #[Test]
    public function not_true_rejects_everything(): void
    {
        $validator = new NotValidator($this->dependencies);
        $schema = new Schema(not: true);

        $this->expectException(ValidationException::class);

        $validator->validate('anything', $schema);
    }

    #[Test]
    public function not_false_is_noop(): void
    {
        $validator = new NotValidator($this->dependencies);
        $schema = new Schema(not: false);

        $validator->validate('anything', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function if_true_routes_to_then_branch(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(
            if: true,
            then: new Schema(type: 'string'),
            else: new Schema(type: 'integer'),
        );

        // If-true → then activates; then:string accepts.
        $validator->validate('hello', $schema);

        // If-true → then:string rejects integer (typed TypeMismatchError
        // surfaces from the recursive validator, not a wrapped one).
        $this->expectException(TypeMismatchError::class);
        $validator->validate(42, $schema);
    }

    #[Test]
    public function if_false_routes_to_else_branch(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(
            if: false,
            then: new Schema(type: 'string'),
            else: new Schema(type: 'integer'),
        );

        // If-false → else activates; else:integer accepts.
        $validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function if_false_then_string_rejects_with_else_active(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(
            if: false,
            then: new Schema(type: 'string'),
            else: new Schema(type: 'integer'),
        );

        // If-false → else:integer rejects string with typed TypeMismatchError.
        $this->expectException(TypeMismatchError::class);
        $validator->validate('hello', $schema);
    }

    #[Test]
    public function if_true_with_then_false_rejects(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(if: true, then: false);

        $this->expectException(ValidationException::class);
        $validator->validate('anything', $schema);
    }

    #[Test]
    public function if_false_with_else_false_rejects(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(if: false, else: false);

        $this->expectException(ValidationException::class);
        $validator->validate('anything', $schema);
    }

    #[Test]
    public function if_true_with_then_true_is_noop(): void
    {
        $validator = new IfThenElseValidator($this->dependencies);
        $schema = new Schema(if: true, then: true);

        $validator->validate('anything', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unevaluated_items_true_is_noop(): void
    {
        $validator = new UnevaluatedItemsValidator($this->dependencies);
        $schema = new Schema(type: 'array', unevaluatedItems: true);

        $validator->validate([1, 'a', null], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unevaluated_items_false_rejects_unevaluated_items(): void
    {
        $validator = new UnevaluatedItemsValidator($this->dependencies);
        $schema = new Schema(type: 'array', unevaluatedItems: false);

        $this->expectException(ValidationException::class);

        $validator->validate([1, 'a'], $schema);
    }

    #[Test]
    public function unevaluated_items_false_passes_when_all_items_evaluated(): void
    {
        $validator = new UnevaluatedItemsValidator($this->dependencies);
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
            unevaluatedItems: false,
        );

        $validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }
}
