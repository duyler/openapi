<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        self::assertStringContainsString('array_unique($data, SORT_REGULAR)', $code);
        self::assertStringContainsString('Array items must be unique', $code);
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

        self::assertStringContainsString('in_array($item', $code);
        self::assertStringContainsString('Invalid enum value in array', $code);
    }
}
