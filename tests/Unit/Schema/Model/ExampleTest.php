<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Example;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Example::class)]
final class ExampleTest extends TestCase
{
    #[Test]
    public function can_create_example_with_all_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            dataValue: ['decoded' => 'data'],
            serializedValue: 'SGVsbG8gV29ybGQ=',
            externalValue: 'https://example.com/example',
            serializedExample: 'https://example.com/serialized',
        );

        self::assertSame('Example', $example->summary);
        self::assertSame('Description', $example->description);
        self::assertSame(['test' => 'value'], $example->value);
        self::assertSame(['decoded' => 'data'], $example->dataValue);
        self::assertSame('SGVsbG8gV29ybGQ=', $example->serializedValue);
        self::assertSame('https://example.com/example', $example->externalValue);
        self::assertSame('https://example.com/serialized', $example->serializedExample);
    }

    #[Test]
    public function can_create_example_with_null_fields(): void
    {
        $example = new Example(
            summary: null,
            description: null,
            value: null,
            dataValue: null,
            serializedValue: null,
            externalValue: null,
            serializedExample: null,
        );

        self::assertNull($example->summary);
        self::assertNull($example->description);
        self::assertNull($example->value);
        self::assertNull($example->dataValue);
        self::assertNull($example->serializedValue);
        self::assertNull($example->externalValue);
        self::assertNull($example->serializedExample);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            dataValue: ['decoded' => 'data'],
            serializedValue: 'SGVsbG8gV29ybGQ=',
            externalValue: null,
            serializedExample: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('value', $serialized);
        self::assertArrayHasKey('dataValue', $serialized);
        self::assertArrayHasKey('serializedValue', $serialized);
        self::assertSame('Example', $serialized['summary']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $example = new Example(
            summary: null,
            description: null,
            value: null,
            dataValue: null,
            serializedValue: null,
            externalValue: null,
            serializedExample: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('summary', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('value', $serialized);
        self::assertArrayNotHasKey('dataValue', $serialized);
        self::assertArrayNotHasKey('serializedValue', $serialized);
        self::assertArrayNotHasKey('serializedExample', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            dataValue: ['key' => 'value'],
            serializedValue: 'encoded',
            externalValue: null,
            serializedExample: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('value', $serialized);
        self::assertArrayHasKey('dataValue', $serialized);
        self::assertArrayHasKey('serializedValue', $serialized);
    }

    #[Test]
    public function json_serialize_includes_externalValue(): void
    {
        $example = new Example(
            externalValue: 'https://example.com/example',
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('externalValue', $serialized);
    }

    #[Test]
    public function example_has_data_value(): void
    {
        $example = new Example(
            summary: 'Test example',
            dataValue: ['name' => 'John', 'age' => 30],
        );

        self::assertSame(['name' => 'John', 'age' => 30], $example->dataValue);
    }

    #[Test]
    public function example_has_serialized_value(): void
    {
        $example = new Example(
            summary: 'Binary example',
            serializedValue: 'SGVsbG8gV29ybGQ=',
        );

        self::assertSame('SGVsbG8gV29ybGQ=', $example->serializedValue);
    }

    #[Test]
    public function example_has_serialized_example(): void
    {
        $example = new Example(
            summary: 'External serialized',
            serializedExample: 'https://example.com/serialized.json',
        );

        self::assertSame('https://example.com/serialized.json', $example->serializedExample);
    }

    #[Test]
    public function json_serialize_includes_serialized_example(): void
    {
        $example = new Example(
            serializedExample: 'https://example.com/serialized.json',
        );

        $serialized = $example->jsonSerialize();

        self::assertArrayHasKey('serializedExample', $serialized);
        self::assertSame('https://example.com/serialized.json', $serialized['serializedExample']);
    }
}
