<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\JsonContainer;
use Duyler\OpenApi\Validator\Schema\JsonContainerKind;
use Duyler\OpenApi\Validator\Schema\JsonShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use stdClass;

#[CoversClass(JsonShape::class)]
final class JsonShapeTest extends TestCase
{
    #[Test]
    public function object_values_returns_values_for_object_container(): void
    {
        $data = new JsonContainer(JsonContainerKind::Object, ['name' => 'duyler']);

        $values = JsonShape::objectValues($data);

        self::assertSame(['name' => 'duyler'], $values);
    }

    #[Test]
    public function object_values_returns_null_for_array_container(): void
    {
        $data = new JsonContainer(JsonContainerKind::Array, [1, 2]);

        self::assertNull(JsonShape::objectValues($data));
    }

    #[Test]
    public function object_values_treats_empty_plain_array_as_object(): void
    {
        $values = JsonShape::objectValues([]);

        self::assertSame([], $values);
    }

    #[Test]
    public function object_values_treats_assoc_plain_array_as_object(): void
    {
        $values = JsonShape::objectValues(['a' => 1]);

        self::assertSame(['a' => 1], $values);
    }

    #[Test]
    public function object_values_returns_null_for_list_plain_array(): void
    {
        self::assertNull(JsonShape::objectValues([1, 2, 3]));
    }

    #[Test]
    public function object_values_returns_null_for_scalar(): void
    {
        self::assertNull(JsonShape::objectValues('text'));
        self::assertNull(JsonShape::objectValues(42));
        self::assertNull(JsonShape::objectValues(null));
        self::assertNull(JsonShape::objectValues(true));
    }

    #[Test]
    public function object_values_returns_null_for_stdclass(): void
    {
        self::assertNull(JsonShape::objectValues(new stdClass()));
    }

    #[Test]
    public function array_values_returns_values_for_array_container(): void
    {
        $data = new JsonContainer(JsonContainerKind::Array, [1, 2]);

        $values = JsonShape::arrayValues($data);

        self::assertSame([1, 2], $values);
    }

    #[Test]
    public function array_values_returns_null_for_object_container(): void
    {
        $data = new JsonContainer(JsonContainerKind::Object, ['a' => 1]);

        self::assertNull(JsonShape::arrayValues($data));
    }

    #[Test]
    public function array_values_treats_empty_plain_array_as_array(): void
    {
        $values = JsonShape::arrayValues([]);

        self::assertSame([], $values);
    }

    #[Test]
    public function array_values_returns_list_plain_array(): void
    {
        $values = JsonShape::arrayValues([1, 2, 3]);

        self::assertSame([1, 2, 3], $values);
    }

    #[Test]
    public function array_values_returns_null_for_assoc_plain_array(): void
    {
        self::assertNull(JsonShape::arrayValues(['a' => 1]));
    }

    #[Test]
    public function array_values_returns_null_for_scalar(): void
    {
        self::assertNull(JsonShape::arrayValues('text'));
        self::assertNull(JsonShape::arrayValues(null));
    }

    #[Test]
    public function is_object_instance_reflects_object_values_presence(): void
    {
        self::assertTrue(JsonShape::isObjectInstance(new JsonContainer(JsonContainerKind::Object, [])));
        self::assertTrue(JsonShape::isObjectInstance(['key' => 'value']));
        self::assertTrue(JsonShape::isObjectInstance([]));
        self::assertFalse(JsonShape::isObjectInstance([1, 2]));
        self::assertFalse(JsonShape::isObjectInstance('text'));
    }

    #[Test]
    public function is_array_instance_reflects_array_values_presence(): void
    {
        self::assertTrue(JsonShape::isArrayInstance(new JsonContainer(JsonContainerKind::Array, [])));
        self::assertTrue(JsonShape::isArrayInstance([1, 2]));
        self::assertTrue(JsonShape::isArrayInstance([]));
        self::assertFalse(JsonShape::isArrayInstance(['key' => 'value']));
        self::assertFalse(JsonShape::isArrayInstance('text'));
    }

    #[Test]
    public function container_values_returns_container_values(): void
    {
        $data = new JsonContainer(JsonContainerKind::Object, ['a' => 1]);

        self::assertSame(['a' => 1], JsonShape::containerValues($data));
    }

    #[Test]
    public function container_values_returns_plain_array(): void
    {
        self::assertSame([1, 2], JsonShape::containerValues([1, 2]));
    }
}
