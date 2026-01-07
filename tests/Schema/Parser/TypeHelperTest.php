<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\TypeHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

final class TypeHelperTest extends TestCase
{
    #[Test]
    public function as_array_returns_array(): void
    {
        $result = TypeHelper::asArray(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function as_array_throws_on_non_array(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asArray('not an array');
    }

    #[Test]
    public function as_array_or_null_returns_array(): void
    {
        $result = TypeHelper::asArrayOrNull(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function as_array_or_null_returns_null(): void
    {
        $result = TypeHelper::asArrayOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_array_or_null_throws_on_non_array(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asArrayOrNull('not an array');
    }

    #[Test]
    public function as_string_map_or_null_returns_map(): void
    {
        $result = TypeHelper::asStringMapOrNull(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function as_string_map_or_null_returns_null(): void
    {
        $result = TypeHelper::asStringMapOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_string_map_or_null_throws_on_non_string_keys(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringMapOrNull([123 => 'value']);
    }

    #[Test]
    public function as_string_map_or_null_throws_on_non_string_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringMapOrNull(['key' => 123]);
    }

    #[Test]
    public function as_string_map_returns_map(): void
    {
        $result = TypeHelper::asStringMap(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function as_string_map_throws_on_non_string_keys(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringMap([123 => 'value']);
    }

    #[Test]
    public function as_list_returns_list(): void
    {
        $result = TypeHelper::asList(['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function as_list_returns_numeric_keys(): void
    {
        $result = TypeHelper::asList([0 => 'a', 1 => 'b']);
        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function as_list_throws_on_non_array(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asList('not an array');
    }

    #[Test]
    public function as_list_or_null_returns_list(): void
    {
        $result = TypeHelper::asListOrNull(['a', 'b']);
        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function as_list_or_null_returns_null(): void
    {
        $result = TypeHelper::asListOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_list_or_null_throws_on_non_array(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asListOrNull('not an array');
    }

    #[Test]
    public function as_string_returns_string(): void
    {
        $result = TypeHelper::asString('test');
        $this->assertSame('test', $result);
    }

    #[Test]
    public function as_string_throws_on_non_string(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asString(123);
    }

    #[Test]
    public function as_string_or_null_returns_string(): void
    {
        $result = TypeHelper::asStringOrNull('test');
        $this->assertSame('test', $result);
    }

    #[Test]
    public function as_string_or_null_returns_null(): void
    {
        $result = TypeHelper::asStringOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_string_or_null_throws_on_non_string(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringOrNull(123);
    }

    #[Test]
    public function as_int_or_null_returns_int(): void
    {
        $result = TypeHelper::asIntOrNull(42);
        $this->assertSame(42, $result);
    }

    #[Test]
    public function as_int_or_null_returns_null(): void
    {
        $result = TypeHelper::asIntOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_int_or_null_throws_on_non_int(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asIntOrNull('42');
    }

    #[Test]
    public function as_float_or_null_returns_float(): void
    {
        $result = TypeHelper::asFloatOrNull(3.14);
        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function as_float_or_null_converts_int_to_float(): void
    {
        $result = TypeHelper::asFloatOrNull(42);
        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function as_float_or_null_returns_null(): void
    {
        $result = TypeHelper::asFloatOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_float_or_null_throws_on_non_float(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asFloatOrNull('3.14');
    }

    #[Test]
    public function as_bool_or_null_returns_bool(): void
    {
        $result = TypeHelper::asBoolOrNull(true);
        $this->assertTrue($result);
    }

    #[Test]
    public function as_bool_or_null_returns_null(): void
    {
        $result = TypeHelper::asBoolOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_bool_or_null_throws_on_non_bool(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asBoolOrNull('true');
    }

    #[Test]
    public function as_string_list_returns_list(): void
    {
        $result = TypeHelper::asStringList(['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function as_string_list_throws_on_non_string_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringList(['a', 123, 'c']);
    }

    #[Test]
    public function as_string_list_or_null_returns_list(): void
    {
        $result = TypeHelper::asStringListOrNull(['a', 'b']);
        $this->assertSame(['a', 'b'], $result);
    }

    #[Test]
    public function as_string_list_or_null_returns_null(): void
    {
        $result = TypeHelper::asStringListOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_string_list_or_null_throws_on_non_string_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asStringListOrNull(['a', 123]);
    }

    #[Test]
    public function as_security_list_map_or_null_returns_list(): void
    {
        $result = TypeHelper::asSecurityListMapOrNull([
            ['scheme1' => ['scope1']],
            ['scheme2' => ['scope2', 'scope3']],
        ]);
        $this->assertSame([
            ['scheme1' => ['scope1']],
            ['scheme2' => ['scope2', 'scope3']],
        ], $result);
    }

    #[Test]
    public function as_security_list_map_or_null_returns_null(): void
    {
        $result = TypeHelper::asSecurityListMapOrNull(null);
        $this->assertNull($result);
    }

    #[Test]
    public function as_security_list_map_or_null_throws_on_non_list_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asSecurityListMapOrNull([['scheme1' => ['scope1']], 'not a list']);
    }

    #[Test]
    public function as_security_list_map_or_null_throws_on_non_string_inner_keys(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asSecurityListMapOrNull([[123 => ['scope1']]]);
    }

    #[Test]
    public function as_security_list_map_or_null_throws_on_non_list_inner_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asSecurityListMapOrNull([['scheme1' => 'not a list']]);
    }

    #[Test]
    public function as_security_list_map_or_null_throws_on_non_string_inner_values(): void
    {
        $this->expectException(TypeError::class);
        TypeHelper::asSecurityListMapOrNull([['scheme1' => [123]]]);
    }
}
