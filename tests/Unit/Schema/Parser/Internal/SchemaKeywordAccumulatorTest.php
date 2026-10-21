<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\Internal\SchemaKeywordAccumulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaKeywordAccumulator::class)]
final class SchemaKeywordAccumulatorTest extends TestCase
{
    private SchemaKeywordAccumulator $accumulator;

    protected function setUp(): void
    {
        $this->accumulator = new SchemaKeywordAccumulator(
            static fn(bool|array $data): Schema => new Schema(type: 'nested'),
        );
    }

    #[Test]
    public function schema_or_bool_or_null_returns_null_for_missing_key(): void
    {
        self::assertNull($this->accumulator->schemaOrBoolOrNull([], 'items'));
    }

    #[Test]
    public function schema_or_bool_or_null_returns_null_for_null_value(): void
    {
        self::assertNull($this->accumulator->schemaOrBoolOrNull(['items' => null], 'items'));
    }

    #[Test]
    public function schema_or_bool_or_null_returns_bool_values(): void
    {
        self::assertTrue($this->accumulator->schemaOrBoolOrNull(['items' => true], 'items'));
        self::assertFalse($this->accumulator->schemaOrBoolOrNull(['items' => false], 'items'));
    }

    #[Test]
    public function schema_or_bool_or_null_builds_schema_from_array_value(): void
    {
        $schema = $this->accumulator->schemaOrBoolOrNull(['items' => ['a' => 1]], 'items');

        self::assertInstanceOf(Schema::class, $schema);
        self::assertSame('nested', $schema->type);
    }

    #[Test]
    #[DataProvider('garbage_value_provider')]
    public function schema_or_bool_or_null_throws_with_key_and_type(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "items", got ' . $type);

        $this->accumulator->schemaOrBoolOrNull(['items' => $value], 'items');
    }

    #[Test]
    public function schema_list_returns_null_for_missing_key(): void
    {
        self::assertNull($this->accumulator->schemaList([], 'prefixItems'));
    }

    #[Test]
    public function schema_list_returns_null_for_null_value(): void
    {
        self::assertNull($this->accumulator->schemaList(['prefixItems' => null], 'prefixItems'));
    }

    #[Test]
    public function schema_list_builds_list_from_bool_and_array_elements(): void
    {
        $list = $this->accumulator->schemaList(['prefixItems' => [['a' => 1], false]], 'prefixItems');

        self::assertCount(2, $list);
        self::assertInstanceOf(Schema::class, $list[0]);
        self::assertInstanceOf(Schema::class, $list[1]);
    }

    #[Test]
    public function schema_list_returns_empty_list_for_empty_value(): void
    {
        self::assertSame([], $this->accumulator->schemaList(['prefixItems' => []], 'prefixItems'));
    }

    #[Test]
    #[DataProvider('garbage_value_provider')]
    public function schema_list_throws_with_key_and_type_on_garbage_element(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array or boolean for schema keyword "allOf", got ' . $type);

        $this->accumulator->schemaList(['allOf' => [['a' => 1], $value]], 'allOf');
    }

    #[Test]
    #[DataProvider('non_array_list_value_provider')]
    public function schema_list_throws_invalid_schema_exception_on_non_array_value(mixed $value, string $type): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Expected array for schema keyword "allOf", got ' . $type);

        $this->accumulator->schemaList(['allOf' => $value], 'allOf');
    }

    public static function garbage_value_provider(): array
    {
        return [
            'int' => [42, 'int'],
            'float' => [1.5, 'float'],
            'string' => ['str', 'string'],
        ];
    }

    public static function non_array_list_value_provider(): array
    {
        return [
            'int' => [42, 'int'],
            'float' => [1.5, 'float'],
            'string' => ['str', 'string'],
            'bool' => [true, 'bool'],
        ];
    }
}
