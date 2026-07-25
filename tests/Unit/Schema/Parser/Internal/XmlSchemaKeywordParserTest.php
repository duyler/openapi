<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\Internal\XmlSchemaKeywordParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlSchemaKeywordParser::class)]
final class XmlSchemaKeywordParserTest extends TestCase
{
    #[Test]
    public function build_returns_null_when_xml_data_is_empty(): void
    {
        $xml = new XmlSchemaKeywordParser()->build([]);

        self::assertInstanceOf(Xml::class, $xml);
        self::assertNull($xml->name);
        self::assertNull($xml->nodeType);
    }

    #[Test]
    public function build_returns_xml_with_all_fields(): void
    {
        $xml = new XmlSchemaKeywordParser()->build([
            'name' => 'user',
            'namespace' => 'urn:example',
            'prefix' => 'ex',
            'attribute' => true,
            'wrapped' => false,
            'nodeType' => 'element',
        ]);

        self::assertSame('user', $xml->name);
        self::assertSame('urn:example', $xml->namespace);
        self::assertSame('ex', $xml->prefix);
        self::assertTrue($xml->attribute);
        self::assertFalse($xml->wrapped);
        self::assertSame('element', $xml->nodeType);
    }

    #[Test]
    public function build_throws_on_invalid_node_type(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid XML nodeType "invalid"');

        new XmlSchemaKeywordParser()->build(['nodeType' => 'invalid']);
    }

    #[Test]
    public function build_under_3_2_warns_on_attribute_deprecation(): void
    {
        $logger = new DeprecationLogger(enabled: true);
        $parser = new XmlSchemaKeywordParser('3.2.0', $logger);

        $parser->build(['attribute' => true]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_under_3_1_skips_attribute_deprecation_warning(): void
    {
        $parser = new XmlSchemaKeywordParser('3.1.0');

        $xml = $parser->build(['attribute' => true]);

        self::assertTrue($xml->attribute);
    }
}
