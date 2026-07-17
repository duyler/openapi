<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model\Enum;

use Duyler\OpenApi\Schema\Model\Enum\XmlNodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlNodeType::class)]
final class XmlNodeTypeTest extends TestCase
{
    #[Test]
    public function cases_contain_all_xml_node_types(): void
    {
        $values = array_map(static fn(XmlNodeType $type): string => $type->value, XmlNodeType::cases());

        self::assertSame(
            ['element', 'attribute', 'text', 'cdata', 'none'],
            $values,
        );
    }

    #[Test]
    public function try_from_returns_case_for_valid_value(): void
    {
        self::assertSame(XmlNodeType::Element, XmlNodeType::tryFrom('element'));
        self::assertSame(XmlNodeType::Attribute, XmlNodeType::tryFrom('attribute'));
        self::assertSame(XmlNodeType::Text, XmlNodeType::tryFrom('text'));
        self::assertSame(XmlNodeType::Cdata, XmlNodeType::tryFrom('cdata'));
        self::assertSame(XmlNodeType::None, XmlNodeType::tryFrom('none'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        self::assertNull(XmlNodeType::tryFrom('invalid'));
        self::assertNull(XmlNodeType::tryFrom('ELEMENT'));
        self::assertNull(XmlNodeType::tryFrom(''));
    }
}
