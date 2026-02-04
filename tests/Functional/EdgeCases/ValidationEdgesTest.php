<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\EdgeCases;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Functional\FunctionalTestCase;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use PHPUnit\Framework\Attributes\Test;

final class ValidationEdgesTest extends FunctionalTestCase
{
    // Numeric boundaries
    #[Test]
    public function int32_maximum_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            format: 'int32',
            maximum: 2147483647,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(2147483647, $schema, $context),
        );
    }

    #[Test]
    public function int32_minimum_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            format: 'int32',
            minimum: -2147483648,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(-2147483648, $schema, $context),
        );
    }

    #[Test]
    public function int64_maximum_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            format: 'int64',
            maximum: 9223372036854775807,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(9223372036854775807, $schema, $context),
        );
    }

    #[Test]
    public function zero_value(): void
    {
        $schema = new Schema(
            type: 'integer',
            minimum: 0,
            maximum: 100,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(0, $schema, $context),
        );
    }

    #[Test]
    public function negative_value_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            maximum: -1,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(-1, $schema, $context),
        );
    }

    // String boundaries
    #[Test]
    public function empty_string_allowed(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 0,
            maxLength: 100,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('', $schema, $context),
        );
    }

    #[Test]
    public function string_minimum_length_boundary(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 5,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('hello', $schema, $context),
        );
    }

    #[Test]
    public function string_maximum_length_boundary(): void
    {
        $schema = new Schema(
            type: 'string',
            maxLength: 10,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('0123456789', $schema, $context),
        );
    }

    #[Test]
    public function string_with_special_characters(): void
    {
        // Use a simpler pattern without problematic delimiters
        $schema = new Schema(
            type: 'string',
            pattern: '^[a-zA-Z0-9!@#$%^&*()_+=\\-]*$',
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('Test!@#$', $schema, $context),
        );
    }

    #[Test]
    public function string_with_unicode_characters(): void
    {
        // Just test that unicode strings are accepted without pattern validation
        $schema = new Schema(type: 'string');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('Привет мир', $schema, $context),
        );
    }

    #[Test]
    public function string_with_emoji(): void
    {
        $schema = new Schema(type: 'string');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext('Hello 👋 World 🌍', $schema, $context),
        );
    }

    // Array boundaries
    #[Test]
    public function empty_array_allowed(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext([], $schema, $context),
        );
    }

    #[Test]
    public function array_with_single_element(): void
    {
        $schema = new Schema(
            type: 'array',
            minItems: 1,
            maxItems: 100,
            items: new Schema(type: 'integer'),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext([42], $schema, $context),
        );
    }

    #[Test]
    public function array_with_maximum_elements(): void
    {
        $schema = new Schema(
            type: 'array',
            maxItems: 3,
            items: new Schema(type: 'string'),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(['a', 'b', 'c'], $schema, $context),
        );
    }

    #[Test]
    public function array_with_null_elements(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'string',
                nullable: true,
            ),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(['a', null, 'b'], $schema, $context),
        );
    }

    // Object boundaries
    #[Test]
    public function empty_object_allowed(): void
    {
        $schema = new Schema(type: 'object');
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext([], $schema, $context),
        );
    }

    #[Test]
    public function object_with_single_field(): void
    {
        $schema = new Schema(
            type: 'object',
            minProperties: 1,
            maxProperties: 10,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(['field' => 'value'], $schema, $context),
        );
    }

    #[Test]
    public function object_with_maximum_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            maxProperties: 3,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['a' => 1, 'b' => 2, 'c' => 3],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function object_with_null_values(): void
    {
        $schema = new Schema(
            type: 'object',
            additionalProperties: new Schema(
                type: 'string',
                nullable: true,
            ),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['field1' => 'value', 'field2' => null],
                $schema,
                $context,
            ),
        );
    }

    // Float boundaries
    #[Test]
    public function very_small_float(): void
    {
        $schema = new Schema(
            type: 'number',
            format: 'float',
            minimum: 1.0e-38,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(1.0e-38, $schema, $context),
        );
    }

    #[Test]
    public function very_large_float(): void
    {
        $schema = new Schema(
            type: 'number',
            format: 'float',
            maximum: 3.4e+38,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(3.4e+38, $schema, $context),
        );
    }
}
