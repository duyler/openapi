<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\JsonContainer;
use Duyler\OpenApi\Validator\Schema\JsonContainerKind;
use Duyler\OpenApi\Validator\Schema\JsonInstanceNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use stdClass;

#[CoversClass(JsonInstanceNormalizer::class)]
final class JsonInstanceNormalizerTest extends TestCase
{
    #[Test]
    public function empty_stdclass_becomes_object_container(): void
    {
        $normalized = JsonInstanceNormalizer::normalize(new stdClass());

        self::assertEquals(new JsonContainer(JsonContainerKind::Object, []), $normalized);
    }

    #[Test]
    public function stdclass_with_string_keys_stays_plain_array(): void
    {
        $object = new stdClass();
        $object->name = 'duyler';

        $normalized = JsonInstanceNormalizer::normalize($object);

        self::assertSame(['name' => 'duyler'], $normalized);
    }

    #[Test]
    public function stdclass_with_sequential_integer_keys_becomes_object_container(): void
    {
        $object = new stdClass();
        $object->{'0'} = 'a';
        $object->{'1'} = 'b';

        $normalized = JsonInstanceNormalizer::normalize($object);

        self::assertEquals(new JsonContainer(JsonContainerKind::Object, ['a', 'b']), $normalized);
    }

    #[Test]
    public function empty_array_becomes_array_container(): void
    {
        $normalized = JsonInstanceNormalizer::normalize([]);

        self::assertEquals(new JsonContainer(JsonContainerKind::Array, []), $normalized);
    }

    #[Test]
    public function non_empty_array_stays_plain_list(): void
    {
        $normalized = JsonInstanceNormalizer::normalize([1, 2, 3]);

        self::assertSame([1, 2, 3], $normalized);
    }

    #[Test]
    public function assoc_array_stays_plain_array(): void
    {
        $normalized = JsonInstanceNormalizer::normalize(['a' => 1]);

        self::assertSame(['a' => 1], $normalized);
    }

    #[Test]
    public function scalars_pass_through_unchanged(): void
    {
        self::assertSame('text', JsonInstanceNormalizer::normalize('text'));
        self::assertSame(42, JsonInstanceNormalizer::normalize(42));
        self::assertSame(1.5, JsonInstanceNormalizer::normalize(1.5));
        self::assertTrue(JsonInstanceNormalizer::normalize(true));
        self::assertNull(JsonInstanceNormalizer::normalize(null));
    }

    #[Test]
    public function nested_stdclass_inside_object_is_normalized(): void
    {
        $inner = new stdClass();
        $object = new stdClass();
        $object->child = $inner;
        $object->items = [];

        $normalized = JsonInstanceNormalizer::normalize($object);

        self::assertEquals([
            'child' => new JsonContainer(JsonContainerKind::Object, []),
            'items' => new JsonContainer(JsonContainerKind::Array, []),
        ], $normalized);
    }

    #[Test]
    public function nested_stdclass_inside_array_is_normalized(): void
    {
        $inner = new stdClass();
        $inner->id = 7;

        $normalized = JsonInstanceNormalizer::normalize([$inner]);

        self::assertSame([['id' => 7]], $normalized);
    }

    #[Test]
    public function nested_array_inside_object_preserves_keys(): void
    {
        $object = new stdClass();
        $object->list = [1, 2];

        $normalized = JsonInstanceNormalizer::normalize($object);

        self::assertSame(['list' => [1, 2]], $normalized);
    }
}
