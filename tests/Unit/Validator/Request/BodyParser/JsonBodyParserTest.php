<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class JsonBodyParserTest extends TestCase
{
    private JsonBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonBodyParser();
    }

    #[Test]
    public function parse_json_body(): void
    {
        $body = '{"name":"John","age":30}';
        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_empty_string_returns_null(): void
    {
        $body = '';

        $this->assertNull($this->parser->parse($body));
    }

    #[Test]
    public function parse_whitespace_only_returns_null(): void
    {
        $body = '   ';

        $this->assertNull($this->parser->parse($body));
    }

    #[Test]
    public function throw_error_for_invalid_json(): void
    {
        $body = '{invalid json}';

        $this->expectException(JsonException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_json_body_with_utf8_bom(): void
    {
        $body = "\xEF\xBB\xBF" . '{"name":"John","age":30}';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_json_body_without_bom_returns_same_result(): void
    {
        $body = '{"name":"John","age":30}';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_body_with_bom_and_invalid_json_throws_json_exception(): void
    {
        $body = "\xEF\xBB\xBF" . '{invalid json}';

        $this->expectException(JsonException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_body_with_only_bom_returns_null(): void
    {
        $body = "\xEF\xBB\xBF";

        $this->assertNull($this->parser->parse($body));
    }

    #[Test]
    public function parse_body_with_double_bom_throws_json_exception(): void
    {
        $body = "\xEF\xBB\xBF\xEF\xBB\xBF" . '{"name":"John"}';

        $this->expectException(JsonException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_json_null_literal_returns_null(): void
    {
        $body = 'null';

        $this->assertNull($this->parser->parse($body));
    }

    #[Test]
    public function parse_invalid_utf8_json_throws_invalid_utf8_exception(): void
    {
        $body = "{\"key\":\"\xC0\x80\"}";

        $this->expectException(InvalidUtf8Exception::class);
        $this->expectExceptionMessage('invalid UTF-8');

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_invalid_utf8_with_bom_stripped_first_throws_invalid_utf8_exception(): void
    {
        $body = "\xEF\xBB\xBF" . "{\"key\":\"\xFF\xFE\"}";

        $this->expectException(InvalidUtf8Exception::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_valid_utf8_json_accepted(): void
    {
        $body = '{"key":"héllo wörld — 中文 — 🎉"}';

        $result = $this->parser->parse($body);

        $this->assertSame(['key' => 'héllo wörld — 中文 — 🎉'], $result);
    }

    #[Test]
    public function parse_iso_latin_1_byte_sequence_rejected(): void
    {
        $body = "{\"key\":\"\xE9\"}";

        $this->expectException(InvalidUtf8Exception::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_bom_followed_by_empty_returns_null(): void
    {
        $result = $this->parser->parse("\xEF\xBB\xBF");

        $this->assertNull($result);
    }
}
