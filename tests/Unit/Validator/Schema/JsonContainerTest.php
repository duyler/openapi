<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\JsonContainer;
use Duyler\OpenApi\Validator\Schema\JsonContainerKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use AssertionError;

#[CoversClass(JsonContainer::class)]
final class JsonContainerTest extends TestCase
{
    #[Test]
    public function stores_kind_and_values(): void
    {
        $container = new JsonContainer(JsonContainerKind::Object, ['a' => 1]);

        self::assertSame(JsonContainerKind::Object, $container->kind);
        self::assertSame(['a' => 1], $container->values);
    }

    #[Test]
    public function is_object_true_only_for_object_kind(): void
    {
        self::assertTrue(new JsonContainer(JsonContainerKind::Object, [])->isObject());
        self::assertFalse(new JsonContainer(JsonContainerKind::Array, [])->isObject());
    }

    #[Test]
    public function is_array_true_only_for_array_kind(): void
    {
        self::assertTrue(new JsonContainer(JsonContainerKind::Array, [])->isArray());
        self::assertFalse(new JsonContainer(JsonContainerKind::Object, [])->isArray());
    }

    #[Test]
    public function array_kind_accepts_list_values(): void
    {
        $container = new JsonContainer(JsonContainerKind::Array, [1, 'a', null]);

        self::assertSame([1, 'a', null], $container->values);
    }

    #[Test]
    public function array_kind_rejects_non_list_values(): void
    {
        $this->expectException(AssertionError::class);

        new JsonContainer(JsonContainerKind::Array, ['a' => 1]);
    }
}
