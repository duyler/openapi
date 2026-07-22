<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UniqueItemsDistinctJsonTypesValidator;
use UniqueItemsNumericEqualityValidator;
use RuntimeException;

final class ArrayValidationTest extends TestCase
{
    #[Test]
    public function compiled_validator_handles_string_arrays(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'StringArrayValidator');

        self::assertStringContainsString('foreach ($data as $index => $item)', $code);
        self::assertStringContainsString('is_string($item)', $code);
    }

    #[Test]
    public function compiled_validator_handles_object_arrays(): void
    {
        $itemSchema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'ObjectArrayValidator');

        self::assertStringContainsString('foreach ($data as $index => $item)', $code);
        self::assertStringContainsString('$item[\'id\']', $code);
        self::assertStringContainsString('$item[\'name\']', $code);
    }

    #[Test]
    public function compiled_validator_validates_min_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
            minItems: 3,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'MinItemsValidator');

        self::assertStringContainsString('count($data) < 3', $code);
        self::assertStringContainsString('Array too short', $code);
    }

    #[Test]
    public function compiled_validator_validates_max_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
            maxItems: 10,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'MaxItemsValidator');

        self::assertStringContainsString('count($data) > 10', $code);
        self::assertStringContainsString('Array too long', $code);
    }

    #[Test]
    public function compiled_validator_validates_unique_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
            uniqueItems: true,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'UniqueItemsValidator');

        self::assertStringContainsString('json_encode', $code);
        self::assertStringContainsString("JSON_THROW_ON_ERROR", $code);
        self::assertStringContainsString('Array items must be unique', $code);
        self::assertStringNotContainsString('SORT_REGULAR', $code);
    }

    #[Test]
    public function compiled_validator_handles_nested_arrays(): void
    {
        $innerItem = new Schema(type: 'integer');
        $innerArray = new Schema(type: 'array', items: $innerItem);
        $outerArray = new Schema(type: 'array', items: $innerArray);

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($outerArray, 'NestedArrayValidator');

        self::assertStringContainsString('foreach ($data as $index => $item)', $code);
    }

    /**
     * R4-CORRECTNESS-013: enum checks inside `items` must use the inlined
     * JsonEquals helper (mirroring the top-level enum path) so int 1 and
     * float 1.0 are equal per JSON Schema 2020-12 §4.2.2. The previous
     * strict `in_array(..., true)` path incorrectly distinguished them.
     */
    #[Test]
    public function compiled_validator_handles_enum_in_arrays(): void
    {
        $itemSchema = new Schema(
            type: 'string',
            enum: ['a', 'b', 'c'],
        );

        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'EnumArrayValidator');

        self::assertStringContainsString('$this->jsonEquals(', $code);
        self::assertStringContainsString('Value must be one of', $code);
        self::assertStringNotContainsString('in_array($item', $code);
    }

    /**
     * Regression for review finding on bugfix 18: the compiled validator
     * must treat int 1 and float 1.0 as duplicates per JSON Schema §4.2.3,
     * matching the runtime ArrayLengthValidator behaviour. The previous
     * SORT_REGULAR-based compiled code relied on PHP loose comparison and
     * also failed this case for a different reason (it was replaced by
     * json_encode which collapses 1 and 1.0 to the same key "1").
     */
    #[Test]
    public function compiled_validator_unique_items_detects_int_and_float_as_duplicate(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'number'),
            uniqueItems: true,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'UniqueItemsNumericEqualityValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new UniqueItemsNumericEqualityValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array items must be unique');

        $validator->validate([1, 1.0]);
    }

    /**
     * The compiled validator must keep distinct JSON types separate:
     * int 1, string "1", and bool true are three distinct values per
     * JSON Schema §6.4.3, and the json_encode-based check produces
     * distinct keys ("1", '"1"', "true") for each.
     */
    #[Test]
    public function compiled_validator_unique_items_keeps_distinct_json_types_separate(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: ['integer', 'string', 'boolean']),
            uniqueItems: true,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'UniqueItemsDistinctJsonTypesValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new UniqueItemsDistinctJsonTypesValidator();

        $validator->validate([1, '1', true]);

        self::assertTrue(class_exists('UniqueItemsDistinctJsonTypesValidator', false));
    }
}
