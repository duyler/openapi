<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class TextBodyParserTest extends TestCase
{
    private TextBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TextBodyParser();
    }

    public static function validTextValuesProvider(): array
    {
        return [
            'simple text' => ['Hello, World!'],
            'empty string' => [''],
            'multiline text' => ["line1\nline2\nline3"],
            'unicode text' => ['Привет мир'],
            'special characters' => ['<script>alert("xss")</script>'],
            'json string' => ['{"key": "value"}'],
            'xml string' => ['<root><item>value</item></root>'],
            'whitespace' => ['   '],
            'tab characters' => ["\t\t"],
            'mixed whitespace' => ["  \n\t  "],
            'emoji' => ['Hello 😀🎉'],
            'chinese' => ['中文测试'],
            'long text' => [str_repeat('a', 10000)],
            'single character' => ['x'],
            'newlines only' => ["\n\n\n"],
            'null byte' => ["text\x00with null"],
        ];
    }

    #[DataProvider('validTextValuesProvider')]
    #[Test]
    public function parse_returns_body_as_is(string $body): void
    {
        $result = $this->parser->parse($body);

        $this->assertSame($body, $result);
    }

    #[Test]
    public function parse_preserves_exact_content(): void
    {
        $body = 'Hello, World! This is a test.';

        $result = $this->parser->parse($body);

        $this->assertSame($body, $result);
    }

    #[Test]
    public function parse_returns_string_type(): void
    {
        $result = $this->parser->parse('test');

        $this->assertIsString($result);
    }

    #[Test]
    public function parse_empty_body_returns_empty_string(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame('', $result);
    }

    #[Test]
    public function parse_does_not_modify_content(): void
    {
        $body = '  spaces around  ';

        $result = $this->parser->parse($body);

        $this->assertSame($body, $result);
    }

    #[Test]
    public function parse_preserves_html_entities(): void
    {
        $body = '&lt;script&gt;alert(1)&lt;/script&gt;';

        $result = $this->parser->parse($body);

        $this->assertSame($body, $result);
    }
}
