<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\EdgeCases;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Functional\FunctionalTestCase;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use PHPUnit\Framework\Attributes\Test;

final class ComplexScenariosTest extends FunctionalTestCase
{
    // Deeply nested structures
    #[Test]
    public function deeply_nested_objects_10_levels(): void
    {
        $schema = $this->createDeepNestingSchema(10);
        $context = $this->createContext(new SimpleFormatter());

        $data = $this->createDeepNestingData(10, 'valid');
        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext($data, $schema, $context),
        );
    }

    #[Test]
    public function deeply_nested_arrays_validation(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'array',
                items: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        );
        $context = $this->createContext(new SimpleFormatter());

        $data = [
            [
                ['a', 'b'],
                ['c', 'd'],
            ],
            [
                ['e', 'f'],
                ['g', 'h'],
            ],
        ];

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext($data, $schema, $context),
        );
    }

    #[Test]
    public function mixed_nesting_arrays_and_objects(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'data' => new Schema(
                    type: 'array',
                    items: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'tags' => new Schema(
                                type: 'array',
                                items: new Schema(type: 'string'),
                            ),
                            'metadata' => new Schema(
                                type: 'object',
                                properties: [
                                    'created' => new Schema(type: 'string'),
                                ],
                            ),
                        ],
                    ),
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $data = [
            'data' => [
                [
                    'name' => 'Item 1',
                    'tags' => ['tag1', 'tag2'],
                    'metadata' => ['created' => '2024-01-01'],
                ],
                [
                    'name' => 'Item 2',
                    'tags' => ['tag3'],
                    'metadata' => ['created' => '2024-01-02'],
                ],
            ],
        ];

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext($data, $schema, $context),
        );
    }

    // Large payloads
    #[Test]
    public function large_object_many_fields(): void
    {
        $properties = [];
        for ($i = 1; $i <= 20; $i++) {
            $properties["field{$i}"] = new Schema(type: 'string');
        }

        $schema = new Schema(
            type: 'object',
            properties: $properties,
        );
        $context = $this->createContext(new SimpleFormatter());

        $data = [];
        for ($i = 1; $i <= 20; $i++) {
            $data["field{$i}"] = "value{$i}";
        }

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext($data, $schema, $context),
        );
    }

    #[Test]
    public function large_array_many_elements(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(type: 'integer'),
                    'value' => new Schema(type: 'string'),
                ],
            ),
        );
        $context = $this->createContext(new SimpleFormatter());

        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = ['id' => $i, 'value' => "item{$i}"];
        }

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext($data, $schema, $context),
        );
    }

    // Special characters handling
    #[Test]
    public function html_entities_in_strings(): void
    {
        $schema = new Schema(type: 'string');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('<div>Hello & goodbye</div>', $schema, $context),
        );
    }

    #[Test]
    public function json_escaping_in_strings(): void
    {
        $schema = new Schema(type: 'string');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('{"key": "value"}', $schema, $context),
        );
    }

    #[Test]
    public function newlines_and_special_chars(): void
    {
        $schema = new Schema(type: 'string');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext("Line 1\nLine 2\tTabbed\r\nCarriage return", $schema, $context),
        );
    }

    // Null vs missing handling
    #[Test]
    public function null_vs_missing_field(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'required_field' => new Schema(type: 'string'),
                'nullable_field' => new Schema(
                    type: 'string',
                    nullable: true,
                ),
                'optional_field' => new Schema(type: 'string'),
            ],
            required: ['required_field'],
        );
        $context = $this->createContext(new SimpleFormatter());

        // null in nullable field should pass
        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['required_field' => 'value', 'nullable_field' => null],
                $schema,
                $context,
            ),
        );

        // missing optional field should pass
        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['required_field' => 'value'],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function empty_string_vs_null(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'empty_string' => new Schema(type: 'string'),
                'nullable_field' => new Schema(
                    type: 'string',
                    nullable: true,
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['empty_string' => '', 'nullable_field' => null],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function empty_array_validation(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'empty_array' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['empty_array' => []],
                $schema,
                $context,
            ),
        );
    }

    // Helper methods
    private function createDeepNestingSchema(int $levels): Schema
    {
        if ($levels === 1) {
            return new Schema(type: 'string');
        }

        return new Schema(
            type: 'object',
            properties: [
                'level' . $levels => $this->createDeepNestingSchema($levels - 1),
            ],
        );
    }

    private function createDeepNestingData(int $levels, string $value): array|string
    {
        if ($levels === 1) {
            return $value;
        }

        return ['level' . $levels => $this->createDeepNestingData($levels - 1, $value)];
    }
}
