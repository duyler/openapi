<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Xml::class)]
final class XmlTest extends TestCase
{
    #[Test]
    public function can_create_xml_with_all_properties(): void
    {
        $xml = new Xml(
            name: 'user',
            namespace: 'https://example.com/ns',
            prefix: 'ex',
            attribute: false,
            wrapped: true,
            nodeType: 'element',
        );

        self::assertSame('user', $xml->name);
        self::assertSame('https://example.com/ns', $xml->namespace);
        self::assertSame('ex', $xml->prefix);
        self::assertFalse($xml->attribute);
        self::assertTrue($xml->wrapped);
        self::assertSame('element', $xml->nodeType);
    }

    #[Test]
    public function can_create_xml_with_null_properties(): void
    {
        $xml = new Xml(
            name: null,
            namespace: null,
            prefix: null,
            attribute: null,
            wrapped: null,
            nodeType: null,
        );

        self::assertNull($xml->name);
        self::assertNull($xml->namespace);
        self::assertNull($xml->prefix);
        self::assertNull($xml->attribute);
        self::assertNull($xml->wrapped);
        self::assertNull($xml->nodeType);
    }

    #[Test]
    public function node_type_defaults_to_null(): void
    {
        $xml = new Xml(name: 'test');

        self::assertNull($xml->nodeType);
    }

    #[Test]
    public function accepts_valid_node_types(): void
    {
        $validTypes = ['element', 'attribute', 'text', 'cdata', 'none'];

        foreach ($validTypes as $type) {
            $xml = new Xml(nodeType: $type);
            self::assertSame($type, $xml->nodeType);
        }
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $xml = new Xml(
            name: 'item',
            namespace: 'https://example.com/ns',
            prefix: 'ex',
            attribute: true,
            wrapped: false,
            nodeType: 'element',
        );

        $result = $xml->jsonSerialize();

        self::assertIsArray($result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('namespace', $result);
        self::assertArrayHasKey('prefix', $result);
        self::assertArrayHasKey('attribute', $result);
        self::assertArrayHasKey('wrapped', $result);
        self::assertArrayHasKey('nodeType', $result);
        self::assertSame('item', $result['name']);
        self::assertSame('element', $result['nodeType']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $xml = new Xml(
            name: 'item',
            namespace: null,
            prefix: null,
            attribute: null,
            wrapped: null,
            nodeType: null,
        );

        $result = $xml->jsonSerialize();

        self::assertIsArray($result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayNotHasKey('namespace', $result);
        self::assertArrayNotHasKey('prefix', $result);
        self::assertArrayNotHasKey('attribute', $result);
        self::assertArrayNotHasKey('wrapped', $result);
        self::assertArrayNotHasKey('nodeType', $result);
    }

    #[Test]
    public function json_serialize_includes_node_type(): void
    {
        $xml = new Xml(
            name: 'value',
            nodeType: 'attribute',
        );

        $result = $xml->jsonSerialize();

        self::assertArrayHasKey('nodeType', $result);
        self::assertSame('attribute', $result['nodeType']);
    }

    #[Test]
    public function json_serialize_includes_deprecated_attribute(): void
    {
        $xml = new Xml(
            name: 'value',
            attribute: true,
        );

        $result = $xml->jsonSerialize();

        self::assertArrayHasKey('attribute', $result);
        self::assertTrue($result['attribute']);
    }

    #[Test]
    public function json_serialize_includes_deprecated_wrapped(): void
    {
        $xml = new Xml(
            name: 'items',
            wrapped: true,
        );

        $result = $xml->jsonSerialize();

        self::assertArrayHasKey('wrapped', $result);
        self::assertTrue($result['wrapped']);
    }

    #[Test]
    public function valid_node_types_constant_contains_expected_values(): void
    {
        self::assertSame(
            ['element', 'attribute', 'text', 'cdata', 'none'],
            Xml::VALID_NODE_TYPES,
        );
    }

    #[Test]
    public function is_valid_node_type_returns_true_for_valid_types(): void
    {
        self::assertTrue(Xml::isValidNodeType('element'));
        self::assertTrue(Xml::isValidNodeType('attribute'));
        self::assertTrue(Xml::isValidNodeType('text'));
        self::assertTrue(Xml::isValidNodeType('cdata'));
        self::assertTrue(Xml::isValidNodeType('none'));
    }

    #[Test]
    public function is_valid_node_type_returns_false_for_invalid_types(): void
    {
        self::assertFalse(Xml::isValidNodeType('invalid'));
        self::assertFalse(Xml::isValidNodeType('ELEMENT'));
        self::assertFalse(Xml::isValidNodeType(''));
        self::assertFalse(Xml::isValidNodeType('Element'));
    }
}
