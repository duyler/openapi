<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MinPropertiesError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;

final class SchemaValidationTest extends TestCase
{
    private ValidatorPool $pool;
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new SchemaValidator($this->pool);
    }

    #[Test]
    public function string_with_min_length_valid(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);
        $this->validator->validate('hello', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_min_length_too_short_throws_error(): void
    {
        $schema = new Schema(type: 'string', minLength: 5);
        $this->expectException(MinLengthError::class);
        $this->validator->validate('hi', $schema);
    }

    #[Test]
    public function string_with_max_length_valid(): void
    {
        $schema = new Schema(type: 'string', maxLength: 10);
        $this->validator->validate('hello', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_max_length_too_long_throws_error(): void
    {
        $schema = new Schema(type: 'string', maxLength: 5);
        $this->expectException(MaxLengthError::class);
        $this->validator->validate('hello world', $schema);
    }

    #[Test]
    public function string_with_pattern_valid(): void
    {
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');
        $this->validator->validate('hello', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_pattern_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');
        $this->expectException(PatternMismatchError::class);
        $this->validator->validate('Hello123', $schema);
    }

    #[Test]
    public function string_with_email_format_valid(): void
    {
        $schema = new Schema(type: 'string', format: 'email');
        $this->validator->validate('test@example.com', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_email_format_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'string', format: 'email');
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not-an-email', $schema);
    }

    #[Test]
    public function string_with_uuid_format_valid(): void
    {
        $schema = new Schema(type: 'string', format: 'uuid');
        $this->validator->validate('550e8400-e29b-41d4-a716-446655440000', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_uuid_format_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'string', format: 'uuid');
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not-a-uuid', $schema);
    }

    #[Test]
    public function string_with_uri_format_valid(): void
    {
        $schema = new Schema(type: 'string', format: 'uri');
        $this->validator->validate('https://example.com/path', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_with_uri_format_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'string', format: 'uri');
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not-a-uri', $schema);
    }

    #[Test]
    public function number_with_minimum_valid(): void
    {
        $schema = new Schema(type: 'number', minimum: 10);
        $this->validator->validate(15.5, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_with_minimum_below_throws_error(): void
    {
        $schema = new Schema(type: 'number', minimum: 10);
        $this->expectException(MinimumError::class);
        $this->validator->validate(5.5, $schema);
    }

    #[Test]
    public function number_with_maximum_valid(): void
    {
        $schema = new Schema(type: 'number', maximum: 100);
        $this->validator->validate(75.5, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_with_maximum_above_throws_error(): void
    {
        $schema = new Schema(type: 'number', maximum: 100);
        $this->expectException(MaximumError::class);
        $this->validator->validate(150.5, $schema);
    }

    #[Test]
    public function number_with_exclusive_minimum_valid(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 10);
        $this->validator->validate(10.1, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_with_exclusive_minimum_equal_throws_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 10);
        $this->expectException(MinimumError::class);
        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function number_with_exclusive_maximum_valid(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 100);
        $this->validator->validate(99.9, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_with_exclusive_maximum_equal_throws_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 100);
        $this->expectException(MaximumError::class);
        $this->validator->validate(100, $schema);
    }

    #[Test]
    public function number_with_multiple_of_valid(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);
        $this->validator->validate(15, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_with_multiple_of_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);
        $this->expectException(MultipleOfKeywordError::class);
        $this->validator->validate(13, $schema);
    }

    #[Test]
    public function integer_with_minimum_valid(): void
    {
        $schema = new Schema(type: 'integer', minimum: 10);
        $this->validator->validate(15, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function integer_with_minimum_below_throws_error(): void
    {
        $schema = new Schema(type: 'integer', minimum: 10);
        $this->expectException(MinimumError::class);
        $this->validator->validate(5, $schema);
    }

    #[Test]
    public function integer_with_maximum_valid(): void
    {
        $schema = new Schema(type: 'integer', maximum: 100);
        $this->validator->validate(75, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function integer_with_maximum_above_throws_error(): void
    {
        $schema = new Schema(type: 'integer', maximum: 100);
        $this->expectException(MaximumError::class);
        $this->validator->validate(150, $schema);
    }

    #[Test]
    public function integer_with_multiple_of_valid(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 5);
        $this->validator->validate(15, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function integer_with_multiple_of_invalid_throws_error(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 5);
        $this->expectException(MultipleOfKeywordError::class);
        $this->validator->validate(13, $schema);
    }

    #[Test]
    public function boolean_true_valid(): void
    {
        $schema = new Schema(type: 'boolean');
        $this->validator->validate(true, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boolean_false_valid(): void
    {
        $schema = new Schema(type: 'boolean');
        $this->validator->validate(false, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_boolean_with_null_valid(): void
    {
        $schema = new Schema(type: 'boolean', nullable: true);
        $this->validator->validate(null, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function null_type_valid(): void
    {
        $schema = new Schema(type: 'null');
        $this->validator->validate(null, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function null_type_with_non_null_throws_error(): void
    {
        $schema = new Schema(type: 'null');
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate('not-null', $schema);
    }

    #[Test]
    public function array_with_min_items_valid(): void
    {
        $schema = new Schema(type: 'array', minItems: 2);
        $this->validator->validate([1, 2, 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_min_items_too_few_throws_error(): void
    {
        $schema = new Schema(type: 'array', minItems: 3);
        $this->expectException(MinItemsError::class);
        $this->validator->validate([1, 2], $schema);
    }

    #[Test]
    public function array_with_max_items_valid(): void
    {
        $schema = new Schema(type: 'array', maxItems: 5);
        $this->validator->validate([1, 2, 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_max_items_too_many_throws_error(): void
    {
        $schema = new Schema(type: 'array', maxItems: 3);
        $this->expectException(MaxItemsError::class);
        $this->validator->validate([1, 2, 3, 4], $schema);
    }

    #[Test]
    public function empty_array_valid(): void
    {
        $schema = new Schema(type: 'array');
        $this->validator->validate([], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_items_schema_valid(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'string'));
        $this->validator->validate(['a', 'b', 'c'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_items_schema_invalid_type_throws_error(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'string'));
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate([1, 2, 3], $schema);
    }

    #[Test]
    public function array_with_prefix_items_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->validator->validate(['hello', 42], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_prefix_items_invalid_type_throws_error(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate([123, 'hello'], $schema);
    }

    #[Test]
    public function array_with_contains_matching_item_valid(): void
    {
        $schema = new Schema(type: 'array', contains: new Schema(type: 'integer'));
        $this->validator->validate(['a', 42, 'b'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_contains_no_matching_item_throws_error(): void
    {
        $schema = new Schema(type: 'array', contains: new Schema(type: 'integer'));
        $this->expectException(ContainsMatchError::class);
        $this->validator->validate(['a', 'b', 'c'], $schema);
    }

    #[Test]
    public function array_with_unique_items_valid(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);
        $this->validator->validate([1, 2, 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_unique_items_duplicate_throws_error(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);
        $this->expectException(DuplicateItemsError::class);
        $this->validator->validate([1, 2, 2, 3], $schema);
    }

    #[Test]
    public function array_with_unique_objects_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            uniqueItems: true,
            items: new Schema(type: 'object'),
        );
        $this->validator->validate([['id' => 1], ['id' => 2]], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_min_contains_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            minContains: 2,
        );
        $this->validator->validate([1, 2, 'a'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_min_contains_too_few_throws_error(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            minContains: 2,
        );
        $this->expectException(MinContainsError::class);
        $this->validator->validate([1, 'a', 'b'], $schema);
    }

    #[Test]
    public function array_with_max_contains_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            maxContains: 2,
        );
        $this->validator->validate([1, 2, 'a'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_with_max_contains_too_many_throws_error(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            maxContains: 2,
        );
        $this->expectException(MaxContainsError::class);
        $this->validator->validate([1, 2, 3, 'a'], $schema);
    }

    #[Test]
    public function nested_arrays_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
            ),
        );
        $this->validator->validate([[1, 2], [3, 4]], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_of_objects_with_arrays_valid(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'tags' => new Schema(
                        type: 'array',
                        items: new Schema(type: 'string'),
                    ),
                ],
            ),
        );
        $this->validator->validate([['tags' => ['a', 'b']], ['tags' => ['c']]], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_required_properties_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name', 'age'],
        );
        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_required_property_missing_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name', 'age'],
        );
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 'John'], $schema);
    }

    #[Test]
    public function object_with_optional_properties_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name'],
        );
        $this->validator->validate(['name' => 'John'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_additional_properties_true_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: true,
        );
        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_additional_properties_false_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: false,
        );
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
    }

    #[Test]
    public function object_with_additional_properties_schema_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: new Schema(type: 'string'),
        );
        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_additional_properties_schema_invalid_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: new Schema(type: 'string'),
        );
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate(['name' => 'John', 'extra' => 123], $schema);
    }

    #[Test]
    public function object_with_pattern_properties_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^S_/' => new Schema(type: 'string'),
            ],
        );
        $this->validator->validate(['S_1' => 'a', 'S_2' => 'b'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_property_names_pattern_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            propertyNames: new Schema(type: 'string', pattern: '^[a-z_]+$'),
        );
        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_property_names_pattern_invalid_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            propertyNames: new Schema(type: 'string', pattern: '^[a-z_]+$'),
        );
        $this->expectException(PatternMismatchError::class);
        $this->validator->validate(['Name' => 'John'], $schema);
    }

    #[Test]
    public function object_with_min_properties_valid(): void
    {
        $schema = new Schema(type: 'object', minProperties: 2);
        $this->validator->validate(['a' => 1, 'b' => 2, 'c' => 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_min_properties_too_few_throws_error(): void
    {
        $schema = new Schema(type: 'object', minProperties: 3);
        $this->expectException(MinPropertiesError::class);
        $this->validator->validate(['a' => 1, 'b' => 2], $schema);
    }

    #[Test]
    public function object_with_max_properties_valid(): void
    {
        $schema = new Schema(type: 'object', maxProperties: 5);
        $this->validator->validate(['a' => 1, 'b' => 2, 'c' => 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_with_max_properties_too_many_throws_error(): void
    {
        $schema = new Schema(type: 'object', maxProperties: 3);
        $this->expectException(MaxPropertiesError::class);
        $this->validator->validate(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $schema);
    }

    #[Test]
    public function empty_object_valid(): void
    {
        $schema = new Schema(type: 'object');
        $this->validator->validate([], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_array_union_type_valid(): void
    {
        $schema = new Schema(type: ['array', 'object']);
        $this->validator->validate([], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_empty_list_as_array_valid(): void
    {
        $schema = new Schema(type: 'array');
        $this->validator->validate([1, 2, 3], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_empty_list_as_object_throws_error(): void
    {
        $schema = new Schema(type: 'object');
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate([1, 2, 3], $schema);
    }

    #[Test]
    public function non_empty_map_as_object_valid(): void
    {
        $schema = new Schema(type: 'object');
        $this->validator->validate(['key' => 'value'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_empty_map_as_array_throws_error(): void
    {
        $schema = new Schema(type: 'array');
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate(['key' => 'value'], $schema);
    }

    #[Test]
    public function object_with_dependent_required_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'creditCard' => new Schema(type: 'string'),
                'billingAddress' => new Schema(type: 'string'),
            ],
            required: ['creditCard'],
        );
        $this->validator->validate(['creditCard' => '1234'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nested_objects_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string'),
                        'address' => new Schema(
                            type: 'object',
                            properties: [
                                'city' => new Schema(type: 'string'),
                            ],
                        ),
                    ],
                ),
            ],
        );
        $this->validator->validate([
            'user' => [
                'name' => 'John',
                'address' => ['city' => 'NYC'],
            ],
        ], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function mixed_types_in_object_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'score' => new Schema(type: 'number'),
                'active' => new Schema(type: 'boolean'),
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ],
        );
        $this->validator->validate([
            'name' => 'John',
            'age' => 30,
            'score' => 95.5,
            'active' => true,
            'tags' => ['a', 'b'],
        ], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function all_of_simple_valid(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')], required: ['name']),
                new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')], required: ['age']),
            ],
        );
        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function all_of_missing_property_throws_error(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')], required: ['name']),
                new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')], required: ['age']),
            ],
        );
        $this->expectException(ValidationException::class);
        $this->validator->validate(['name' => 'John'], $schema);
    }

    #[Test]
    public function all_of_overlapping_properties_valid(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string'),
                        'age' => new Schema(type: 'integer', minimum: 0),
                    ],
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'age' => new Schema(type: 'integer', maximum: 100),
                        'email' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );
        $this->validator->validate(['name' => 'John', 'age' => 30, 'email' => 'test@test.com'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function any_of_simple_valid(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->validator->validate('hello', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function any_of_integer_valid(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->validator->validate(42, $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function any_of_no_match_throws_error(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->expectException(ValidationException::class);
        $this->validator->validate(true, $schema);
    }

    #[Test]
    public function any_of_with_unique_schemas_valid(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(type: 'object', properties: ['type' => new Schema(const: 'user')], required: ['type']),
                new Schema(type: 'object', properties: ['type' => new Schema(const: 'admin')], required: ['type']),
            ],
        );
        $this->validator->validate(['type' => 'user'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function one_of_simple_valid(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->validator->validate('hello', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function one_of_multiple_matches_throws_error(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(properties: ['name' => new Schema(type: 'string')]),
            ],
        );
        $this->expectException(OneOfError::class);
        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function one_of_no_match_throws_error(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $this->expectException(ValidationException::class);
        $this->validator->validate(true, $schema);
    }

    #[Test]
    public function not_simple_valid(): void
    {
        $schema = new Schema(type: 'string', not: new Schema(const: 'forbidden'));
        $this->validator->validate('allowed', $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function not_matching_throws_error(): void
    {
        $schema = new Schema(type: 'string', not: new Schema(const: 'forbidden'));
        $this->expectException(ValidationException::class);
        $this->validator->validate('forbidden', $schema);
    }

    #[Test]
    public function not_complex_schema_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            not: new Schema(
                type: 'object',
                properties: ['secret' => new Schema(type: 'string')],
                required: ['secret'],
            ),
        );
        $this->validator->validate(['public' => 'data'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function conditional_if_then_else_matching_then_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['country' => new Schema(type: 'string')],
            if: new Schema(
                type: 'object',
                properties: ['country' => new Schema(const: 'US')],
                required: ['country'],
            ),
            then: new Schema(
                type: 'object',
                properties: ['zipCode' => new Schema(type: 'string')],
                required: ['zipCode'],
            ),
        );
        $this->validator->validate(['country' => 'US', 'zipCode' => '12345'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function conditional_if_then_else_not_matching_if_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['country' => new Schema(type: 'string')],
            if: new Schema(
                type: 'object',
                properties: ['country' => new Schema(const: 'US')],
                required: ['country'],
            ),
            then: new Schema(
                type: 'object',
                properties: ['zipCode' => new Schema(type: 'string')],
                required: ['zipCode'],
            ),
        );
        $this->validator->validate(['country' => 'CA'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function conditional_if_then_else_with_else_matching_else_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['country' => new Schema(type: 'string')],
            if: new Schema(
                type: 'object',
                properties: ['country' => new Schema(const: 'US')],
                required: ['country'],
            ),
            then: new Schema(
                type: 'object',
                properties: ['zipCode' => new Schema(type: 'string')],
                required: ['zipCode'],
            ),
            else: new Schema(
                type: 'object',
                properties: ['postalCode' => new Schema(type: 'string')],
                required: ['postalCode'],
            ),
        );
        $this->validator->validate(['country' => 'CA', 'postalCode' => 'A1B2C3'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function conditional_only_if_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['country' => new Schema(type: 'string')],
            if: new Schema(
                type: 'object',
                properties: ['country' => new Schema(const: 'US')],
                required: ['country'],
            ),
        );
        $this->validator->validate(['country' => 'US'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function mixed_all_of_any_of_valid(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(
                    type: 'object',
                    properties: ['name' => new Schema(type: 'string')],
                    required: ['name'],
                ),
                new Schema(
                    anyOf: [
                        new Schema(type: 'object', properties: ['age' => new Schema(type: 'integer')], required: ['age']),
                        new Schema(type: 'object', properties: ['score' => new Schema(type: 'number')], required: ['score']),
                    ],
                ),
            ],
        );
        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function mixed_one_of_not_valid(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: ['type' => new Schema(const: 'A')],
                    required: ['type'],
                ),
                new Schema(
                    type: 'object',
                    properties: ['type' => new Schema(const: 'B')],
                    required: ['type'],
                ),
            ],
        );
        $this->validator->validate(['type' => 'A'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nested_composite_schemas_valid(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(
                    type: 'object',
                    anyOf: [
                        new Schema(
                            type: 'object',
                            properties: ['type' => new Schema(const: 'user')],
                            required: ['type'],
                        ),
                        new Schema(
                            type: 'object',
                            properties: ['type' => new Schema(const: 'admin')],
                            required: ['type'],
                        ),
                    ],
                    required: ['type'],
                ),
                new Schema(
                    type: 'object',
                    properties: ['name' => new Schema(type: 'string')],
                    required: ['name'],
                ),
            ],
        );
        $this->validator->validate(['type' => 'user', 'name' => 'John'], $schema);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_items_type_mismatch_throws_error(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'integer'));
        $this->expectException(TypeMismatchError::class);
        $this->validator->validate(['not', 'an', 'integer'], $schema);
    }
}
