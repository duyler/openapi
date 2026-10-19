<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_first;
use function array_keys;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;

#[CoversClass(RefSerializer::class)]
final class RefSerializerTest extends TestCase
{
    #[Test]
    public function to_wire_form_with_ref_summary_and_description(): void
    {
        $result = new RefSerializer()->toWireForm('#/components/schemas/User', 'User summary', 'User description');

        self::assertSame([
            '$ref' => '#/components/schemas/User',
            'summary' => 'User summary',
            'description' => 'User description',
        ], $result);
    }

    #[Test]
    public function to_wire_form_with_ref_only(): void
    {
        $result = new RefSerializer()->toWireForm('#/components/schemas/User', null, null);

        self::assertSame(['$ref' => '#/components/schemas/User'], $result);
    }

    #[Test]
    public function to_wire_form_with_ref_and_summary(): void
    {
        $result = new RefSerializer()->toWireForm('#/components/schemas/User', 'User summary', null);

        self::assertSame([
            '$ref' => '#/components/schemas/User',
            'summary' => 'User summary',
        ], $result);
    }

    #[Test]
    public function to_wire_form_with_ref_and_description(): void
    {
        $result = new RefSerializer()->toWireForm('#/components/schemas/User', null, 'User description');

        self::assertSame([
            '$ref' => '#/components/schemas/User',
            'description' => 'User description',
        ], $result);
    }

    #[Test]
    public function to_wire_form_with_null_ref_returns_null(): void
    {
        self::assertNull(new RefSerializer()->toWireForm(null, 'Ignored summary', 'Ignored description'));
    }

    #[Test]
    public function wire_form_key_order_is_ref_then_summary_then_description(): void
    {
        $result = new RefSerializer()->toWireForm('#/components/schemas/User', 'User summary', 'User description');

        self::assertSame('$ref', array_key_first($result));
        self::assertSame(['$ref', 'summary', 'description'], array_keys($result));
        self::assertSame(
            '{"$ref":"#/components/schemas/User","summary":"User summary","description":"User description"}',
            json_encode($result, JSON_UNESCAPED_SLASHES),
        );
    }
}
