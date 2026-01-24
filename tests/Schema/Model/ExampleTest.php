<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Example;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Example
 */
final class ExampleTest extends TestCase
{
    #[Test]
    public function can_create_example_with_all_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            externalValue: 'https://example.com/example',
        );

        self::assertSame('Example', $example->summary);
        self::assertSame('Description', $example->description);
        self::assertSame(['test' => 'value'], $example->value);
        self::assertSame('https://example.com/example', $example->externalValue);
    }

    #[Test]
    public function can_create_example_with_null_fields(): void
    {
        $example = new Example(
            summary: null,
            description: null,
            value: null,
            externalValue: null,
        );

        self::assertNull($example->summary);
        self::assertNull($example->description);
        self::assertNull($example->value);
        self::assertNull($example->externalValue);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            externalValue: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('value', $serialized);
        self::assertSame('Example', $serialized['summary']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $example = new Example(
            summary: null,
            description: null,
            value: null,
            externalValue: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('summary', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('value', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $example = new Example(
            summary: 'Example',
            description: 'Description',
            value: ['test' => 'value'],
            externalValue: null,
        );

        $serialized = $example->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('value', $serialized);
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
}
