<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

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
}
