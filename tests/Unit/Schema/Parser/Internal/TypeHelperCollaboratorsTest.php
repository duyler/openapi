<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Parser\Internal\ArrayTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\CompositionTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\ScalarTypeHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

#[CoversClass(ScalarTypeHelper::class)]
#[CoversClass(ArrayTypeHelper::class)]
#[CoversClass(ObjectTypeHelper::class)]
#[CoversClass(CompositionTypeHelper::class)]
final class TypeHelperCollaboratorsTest extends TestCase
{
    #[Test]
    public function scalar_as_string_returns_value(): void
    {
        self::assertSame('hello', ScalarTypeHelper::asString('hello'));
    }

    #[Test]
    public function scalar_as_string_throws_on_non_string(): void
    {
        $this->expectException(TypeError::class);
        ScalarTypeHelper::asString(42);
    }

    #[Test]
    public function scalar_as_string_or_null_returns_null_for_null(): void
    {
        self::assertNull(ScalarTypeHelper::asStringOrNull(null));
    }

    #[Test]
    public function scalar_as_int_returns_value(): void
    {
        self::assertSame(42, ScalarTypeHelper::asInt(42));
    }

    #[Test]
    public function scalar_as_int_throws_on_string(): void
    {
        $this->expectException(TypeError::class);
        ScalarTypeHelper::asInt('42');
    }

    #[Test]
    public function scalar_as_float_accepts_int(): void
    {
        self::assertSame(3.0, ScalarTypeHelper::asFloat(3));
    }

    #[Test]
    public function scalar_as_float_or_null_returns_null_for_null(): void
    {
        self::assertNull(ScalarTypeHelper::asFloatOrNull(null));
    }

    #[Test]
    public function scalar_as_bool_returns_value(): void
    {
        self::assertTrue(ScalarTypeHelper::asBool(true));
        self::assertFalse(ScalarTypeHelper::asBool(false));
    }

    #[Test]
    public function array_as_array_returns_value(): void
    {
        self::assertSame(['a' => 1], ArrayTypeHelper::asArray(['a' => 1]));
    }

    #[Test]
    public function array_as_list_returns_numeric_indexed(): void
    {
        self::assertSame(['a', 'b', 'c'], ArrayTypeHelper::asList(['x' => 'a', 'y' => 'b', 'z' => 'c']));
    }

    #[Test]
    public function array_as_string_list_throws_on_non_string_item(): void
    {
        $this->expectException(TypeError::class);
        ArrayTypeHelper::asStringList(['a', 123]);
    }

    #[Test]
    public function array_as_string_list_or_null_returns_null_for_null(): void
    {
        self::assertNull(ArrayTypeHelper::asStringListOrNull(null));
    }

    #[Test]
    public function array_as_enum_list_returns_values(): void
    {
        self::assertSame([1, 'two', 3.0], ArrayTypeHelper::asEnumList([1, 'two', 3.0]));
    }

    #[Test]
    public function object_as_string_map_returns_map(): void
    {
        self::assertSame(['k' => 'v'], ObjectTypeHelper::asStringMap(['k' => 'v']));
    }

    #[Test]
    public function object_as_string_map_throws_on_int_value(): void
    {
        $this->expectException(TypeError::class);
        ObjectTypeHelper::asStringMap(['k' => 1]);
    }

    #[Test]
    public function object_as_string_mixed_map_or_null_preserves_mixed_values(): void
    {
        $result = ObjectTypeHelper::asStringMixedMapOrNull(['a' => 1, 'b' => 'str']);

        self::assertSame(['a' => 1, 'b' => 'str'], $result);
    }

    #[Test]
    public function object_as_string_mixed_map_or_null_throws_on_int_key(): void
    {
        $this->expectException(TypeError::class);
        ObjectTypeHelper::asStringMixedMapOrNull([1 => 'value']);
    }

    #[Test]
    public function composition_as_security_list_map_returns_normalized(): void
    {
        $result = CompositionTypeHelper::asSecurityListMap([
            ['bearerAuth' => []],
            ['oauth2' => ['read', 'write']],
        ]);

        self::assertSame([['bearerAuth' => []], ['oauth2' => ['read', 'write']]], $result);
    }

    #[Test]
    public function composition_as_security_list_map_or_null_returns_null_for_null(): void
    {
        self::assertNull(CompositionTypeHelper::asSecurityListMapOrNull(null));
    }

    #[Test]
    public function composition_as_security_list_map_throws_on_string_item(): void
    {
        $this->expectException(TypeError::class);
        CompositionTypeHelper::asSecurityListMap(['not-an-array']);
    }
}
